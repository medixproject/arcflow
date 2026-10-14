<?php
namespace App\Services;
use App\Models\Invoice;
use App\Models\InvoiceAddressAllocation;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
class LegacyTransferReviewRecorder
{
    public function recordIfLegacyAddress(
        string $network,
        string $tokenContract,
        string $fromAddress,
        string $toAddress,
        string $amount,
        int $blockNumber,
        string $transactionHash,
        int $latestBlock,
        int $logIndex
    ): bool {
        return DB::transaction(function () use (
            $network, $tokenContract, $fromAddress, $toAddress,
            $amount, $blockNumber, $transactionHash, $latestBlock, $logIndex
        ) {
            $to = strtolower($toAddress);
            // HD allocations use the dedicated late-payment recorder.
            if (InvoiceAddressAllocation::query()
                ->where('network', $network)
                ->whereRaw('LOWER(address) = ?', [$to])
                ->exists()) {
                return false;
            }

            // A payment already represents this event; do not create a second review.
            // A transaction-level legacy payment (NULL log_index) blocks conservatively.
            $paymentExists = Payment::query()
                ->where('network', $network)
                ->where('transaction_hash', $transactionHash)
                ->where(function ($query) use ($logIndex) {
                    $query->where('log_index', $logIndex)
                        ->orWhereNull('log_index');
                })
                ->exists();

            if ($paymentExists) {
                return false;
            }
            $invoices = Invoice::query()
                ->where('network', $network)
                ->whereRaw('LOWER(payment_address) = ?', [$to])
                ->where(function ($q) {
                    $q->whereIn('status', ['expired', 'cancelled'])
                      ->orWhere(function ($q) {
                          $q->whereNotNull('expires_at')
                            ->where('expires_at', '<=', now());
                      });
                })
                ->orderBy('id')
                ->get(['id', 'status', 'expires_at']);
            if ($invoices->isEmpty()) {
                return false;
            }
            $existing = DB::table('legacy_transfer_reviews')
                ->where('network', $network)
                ->where('transaction_hash', $transactionHash)
                ->where('log_index', $logIndex)
                ->exists();
            if ($existing) {
                return true;
            }
            $ids = $invoices->pluck('id')->map(fn ($id) => (int) $id)->all();
            DB::table('legacy_transfer_reviews')->insert([
                'network' => $network,
                'token_contract' => strtolower($tokenContract),
                'from_address' => strtolower($fromAddress),
                'to_address' => $to,
                'amount' => $amount,
                'transaction_hash' => $transactionHash,
                'log_index' => $logIndex,
                'block_number' => $blockNumber,
                'confirmations' => max(0, $latestBlock - $blockNumber + 1),
                'candidate_invoice_ids' => json_encode($ids, JSON_THROW_ON_ERROR),
                'review_reason' => count($ids) > 1
                    ? 'duplicate_legacy_address'
                    : 'late_legacy_transfer',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return true;
        }, 3);
    }
}
