<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PaymentMatcher
{
    private function compareDecimal(string $a, string $b): int
    {
        $norm = static function (string $value): array {
            $value = trim($value);
            if (!preg_match('/^\+?[0-9]+(?:\.[0-9]{1,6})?$/D', $value)) {
                throw new \InvalidArgumentException('Payment amounts must be non-negative decimal strings with at most six fractional digits.');
            }
            if (str_starts_with($value, '+')) $value = substr($value, 1);
            $parts = explode('.', $value, 2);
            $integer = ltrim($parts[0], '0');
            $integer = $integer === '' ? '0' : $integer;
            $fraction = rtrim(str_pad($parts[1] ?? '', 6, '0'), '0');
            return [$integer, $fraction];
        };

        [$ai, $af] = $norm($a);
        [$bi, $bf] = $norm($b);
        if (strlen($ai) !== strlen($bi)) return strlen($ai) < strlen($bi) ? -1 : 1;
        if ($ai !== $bi) return $ai < $bi ? -1 : 1;
        $af = str_pad($af, 6, '0');
        $bf = str_pad($bf, 6, '0');
        return $af === $bf ? 0 : ($af < $bf ? -1 : 1);
    }

    /** Normalise a stored/decoded amount (string, int or float from the DB driver) to "I.FFFFFF". */
    private function normalizeAmount(mixed $value): string
    {
        if (is_int($value)) return $value . '.000000';
        if (is_float($value)) return number_format($value, 6, '.', '');
        $value = trim((string) $value);
        if (preg_match('/^[0-9]+$/D', $value)) return $value . '.000000';
        if (preg_match('/^([0-9]+)\.([0-9]{1,6})$/D', $value, $m)) return $m[1] . '.' . str_pad($m[2], 6, '0');
        throw new \InvalidArgumentException('Unexpected amount format.');
    }

    /** Exact decimal addition of two "I.FFFFFF" strings without floats. */
    private function addDecimal(string $a, string $b): string
    {
        $pa = explode('.', $a, 2);
        $pb = explode('.', $b, 2);
        $x = ($pa[0] === '' ? '0' : $pa[0]) . str_pad($pa[1] ?? '', 6, '0');
        $y = ($pb[0] === '' ? '0' : $pb[0]) . str_pad($pb[1] ?? '', 6, '0');
        $len = max(strlen($x), strlen($y));
        $x = str_pad($x, $len, '0', STR_PAD_LEFT);
        $y = str_pad($y, $len, '0', STR_PAD_LEFT);
        $carry = 0;
        $out = '';
        for ($i = $len - 1; $i >= 0; $i--) {
            $sum = (int) $x[$i] + (int) $y[$i] + $carry;
            $out = ($sum % 10) . $out;
            $carry = intdiv($sum, 10);
        }
        if ($carry > 0) $out = $carry . $out;
        $out = str_pad(ltrim($out, '0'), 7, '0', STR_PAD_LEFT);
        return substr($out, 0, -6) . '.' . substr($out, -6);
    }

    /** Sum of all counted payments already recorded for an invoice (partial payments accumulate). */
    private function paidSoFar(int $invoiceId): string
    {
        $total = '0.000000';
        $amounts = Payment::where('invoice_id', $invoiceId)
            ->whereIn('status', ['confirming', 'confirmed', 'underpaid', 'overpaid'])
            ->pluck('amount');
        foreach ($amounts as $recorded) {
            $total = $this->addDecimal($total, $this->normalizeAmount($recorded));
        }
        return $total;
    }

    public function matchTransfer(
        string $network,
        string $token,
        string $from,
        string $to,
        string $amount,
        int $block,
        string $txHash,
        ?int $latestBlock = null,
        ?int $logIndex = null,
    ): ?Payment {
        $payment = DB::transaction(function () use ($network, $token, $from, $to, $amount, $block, $txHash, $latestBlock, $logIndex) {
            $duplicateQuery = Payment::where('network', $network)
                ->where('transaction_hash', $txHash);

            if ($logIndex === null) {
                // Backward-compatible callers retain transaction-level deduplication.
                if ($duplicateQuery->exists()) return null;
            } else {
                // Legacy rows have no log index, so treat them conservatively as duplicates.
                if ($duplicateQuery->where(function ($query) use ($logIndex) {
                    $query->where('log_index', $logIndex)->orWhereNull('log_index');
                })->exists()) return null;
            }

            $invoice = Invoice::where('network', $network)
                ->whereRaw('LOWER(payment_address) = ?', [strtolower($to)])
                ->whereIn('status', ['pending', 'detected', 'confirming', 'underpaid', 'overpaid'])
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->orderBy('created_at')
                ->lockForUpdate()
                ->first();

            if (!$invoice) return null;

            // Validate the incoming amount, then judge the invoice by everything paid so far plus this transfer.
            $this->compareDecimal($amount, '0.000000');
            $total = $this->addDecimal($this->paidSoFar((int) $invoice->id), $this->normalizeAmount($amount));
            $comparison = $this->compareDecimal($total, $this->normalizeAmount($invoice->amount));
            $required = max(1, (int) config('arc.required_confirmations', 1));
            $confirmations = $latestBlock !== null && $block <= $latestBlock ? ($latestBlock - $block + 1) : 1;
            $isExact = $comparison === 0;
            $isConfirmed = $isExact && $confirmations >= $required;
            $status = $comparison < 0 ? 'underpaid' : ($comparison > 0 ? 'overpaid' : ($isConfirmed ? 'confirmed' : 'confirming'));
            $now = Carbon::now();

            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'network' => $network,
                'token_contract' => strtolower($token),
                'from_address' => strtolower($from),
                'to_address' => strtolower($to),
                'amount' => $amount,
                'currency' => 'USDC',
                'transaction_hash' => $txHash,
                'log_index' => $logIndex,
                'block_number' => $block,
                'confirmations' => $confirmations,
                'status' => $status,
                'detected_at' => $now,
                'confirmed_at' => $isConfirmed ? $now : null,
            ]);

            if ($isConfirmed) {
                $invoice->update(['status' => 'paid', 'paid_at' => $now]);
            } elseif ($comparison < 0) {
                $invoice->update(['status' => 'underpaid']);
            } elseif ($comparison > 0) {
                $invoice->update(['status' => 'overpaid']);
            } else {
                $invoice->update(['status' => 'confirming']);
            }
            return $payment->fresh(['invoice']);
        }, 3);

        if ($payment) {
            if ($payment->status === 'confirmed') {
                app(WebhookService::class)->dispatch($payment->invoice, 'invoice.paid', [
                    'payment_id' => $payment->id,
                    'transaction_hash' => $payment->transaction_hash,
                    'block_number' => $payment->block_number,
                    'confirmations' => $payment->confirmations,
                    'paid_amount' => $this->normalizeAmount($payment->invoice->amount),
                ]);
            } elseif (in_array($payment->status, ['underpaid', 'overpaid'], true)) {
                app(WebhookService::class)->dispatch($payment->invoice, 'invoice.payment_detected', [
                    'payment_id' => $payment->id,
                    'transaction_hash' => $payment->transaction_hash,
                    'block_number' => $payment->block_number,
                    'confirmations' => $payment->confirmations,
                    'paid_amount' => (string) $payment->amount,
                    'total_paid' => $this->paidSoFar((int) $payment->invoice_id),
                ]);
            }
        }
        return $payment;
    }

    public function confirmPendingPayments(string $network, int $latestBlock): Collection
    {
        $required = max(1, (int) config('arc.required_confirmations', 1));
        $confirmed = collect();

        Payment::with('invoice')->where('network', $network)->where('status', 'confirming')
            ->whereNotNull('block_number')->orderBy('id')->chunkById(100, function (Collection $payments) use ($latestBlock, $required, &$confirmed) {
                foreach ($payments as $payment) {
                    $confirmations = max(0, $latestBlock - (int) $payment->block_number + 1);
                    if ($confirmations < $required) continue;

                    $changed = DB::transaction(function () use ($payment, $confirmations) {
                        $locked = Payment::with('invoice')->lockForUpdate()->find($payment->id);
                        if (!$locked || $locked->status !== 'confirming' || !$locked->invoice) return null;
                        // Cancelled or expired invoices must never become paid via a delayed confirmation.
                        $invoice = $locked->invoice;
                        $isExpired = $invoice->expires_at !== null
                            && $invoice->expires_at->isPast();

                        if (
                            in_array($invoice->status, ['cancelled', 'expired'], true)
                            || $isExpired
                        ) {
                            $locked->update([
                                'status' => 'late_review',
                                'confirmations' => $confirmations,
                            ]);
                            return null;
                        }
                        $now = Carbon::now();
                        $totalMatches = $this->compareDecimal(
                            $this->paidSoFar((int) $invoice->id),
                            $this->normalizeAmount($invoice->amount)
                        ) === 0;
                        $locked->update(['status' => 'confirmed', 'confirmations' => $confirmations, 'confirmed_at' => $now]);
                        // An extra transfer arrived while confirming: the invoice stays overpaid for manual review.
                        if (!$totalMatches) return null;
                        $locked->invoice->update(['status' => 'paid', 'paid_at' => $now]);
                        return $locked->fresh(['invoice']);
                    }, 3);

                    if ($changed) {
                        $confirmed->push($changed);
                        app(WebhookService::class)->dispatch($changed->invoice, 'invoice.paid', [
                            'payment_id' => $changed->id,
                            'transaction_hash' => $changed->transaction_hash,
                            'block_number' => $changed->block_number,
                            'confirmations' => $changed->confirmations,
                            'paid_amount' => $this->normalizeAmount($changed->invoice->amount),
                        ]);
                    }
                }
            });
        return $confirmed;
    }
}
