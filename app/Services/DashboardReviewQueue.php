<?php

namespace App\Services;

use App\Models\Merchant;
use App\Models\Payment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read-only list of transfers a merchant has to look at by hand:
 * payments that arrived after an invoice expired or was cancelled, and transfers
 * sent to the address of an old (merchant-supplied address) invoice.
 */
class DashboardReviewQueue
{
    public const LIMIT = 100;

    /**
     * @return array{late_payments: Collection, legacy_transfers: Collection}
     */
    public function forMerchant(Merchant $merchant): array
    {
        $network = (string) config('arc.network');

        $latePayments = Payment::query()
            ->with('invoice:id,public_id,invoice_number,amount,currency,status,expires_at')
            ->where('network', $network)
            ->where('status', 'late_review')
            ->whereHas('invoice', fn ($query) => $query->where('merchant_id', $merchant->id))
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get();

        return [
            'late_payments' => $latePayments,
            'legacy_transfers' => $this->legacyTransfers($merchant, $network),
        ];
    }

    private function legacyTransfers(Merchant $merchant, string $network): Collection
    {
        if (!DB::getSchemaBuilder()->hasTable('legacy_transfer_reviews')) {
            return collect();
        }

        $rows = DB::table('legacy_transfer_reviews')
            ->where('network', $network)
            ->whereIn('to_address', function ($query) use ($merchant, $network) {
                $query->from('invoices')
                    ->selectRaw('lower(payment_address)')
                    ->where('merchant_id', $merchant->id)
                    ->where('network', $network);
            })
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        $addresses = $rows->pluck('to_address')->map(fn ($a) => strtolower((string) $a))->unique()->values()->all();

        $invoicesByAddress = $merchant->invoices()
            ->where('network', $network)
            ->whereIn(DB::raw('lower(payment_address)'), $addresses)
            ->get(['id', 'public_id', 'invoice_number', 'payment_address'])
            ->groupBy(fn ($invoice) => strtolower((string) $invoice->payment_address));

        return $rows->map(function ($row) use ($invoicesByAddress) {
            $row->invoices = $invoicesByAddress->get(strtolower((string) $row->to_address), collect());

            return $row;
        })->values();
    }
}
