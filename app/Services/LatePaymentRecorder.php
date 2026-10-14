<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceAddressAllocation;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/** Records transfers to historical HD invoice addresses without marking invoices paid. */
class LatePaymentRecorder
{
    public function recordIfAllocated(
        string $network,
        string $tokenContract,
        string $fromAddress,
        string $toAddress,
        string $amount,
        int $blockNumber,
        string $transactionHash,
        int $latestBlock,
        ?int $logIndex = null
    ): ?Payment {
        return DB::transaction(function () use ($network, $tokenContract, $fromAddress, $toAddress, $amount, $blockNumber, $transactionHash, $latestBlock, $logIndex) {
            $duplicateQuery = Payment::query()
                ->where('network', $network)
                ->where('transaction_hash', $transactionHash);

            if ($logIndex === null) {
                if ($duplicateQuery->exists()) return null;
            } else {
                if ($duplicateQuery->where(function ($query) use ($logIndex) {
                    $query->where('log_index', $logIndex)->orWhereNull('log_index');
                })->exists()) return null;
            }
            $allocation = InvoiceAddressAllocation::query()
                ->where('network', $network)
                ->whereRaw('LOWER(address) = ?', [strtolower($toAddress)])
                ->first();
            if (!$allocation) {
                return null;
            }
            $invoice = Invoice::query()->whereKey($allocation->invoice_id)->first();
            if (!$invoice) {
                return null;
            }
            // Only expired/cancelled addresses are handled here; active invoice matching remains authoritative.
            $isLate = in_array($invoice->status, ['cancelled', 'expired'], true)
                || ($invoice->expires_at !== null && $invoice->expires_at->isPast());
            if (!$isLate) {
                return null;
            }
            return Payment::query()->create([
                'invoice_id' => $invoice->id,
                'network' => $network,
                'token_contract' => strtolower($tokenContract),
                'from_address' => strtolower($fromAddress),
                'to_address' => strtolower($toAddress),
                'amount' => $amount,
                'currency' => $invoice->currency,
                'transaction_hash' => $transactionHash,
                'log_index' => $logIndex,
                'block_number' => $blockNumber,
                'confirmations' => max(0, $latestBlock - $blockNumber + 1),
                'status' => 'late_review',
                'detected_at' => now(),
            ]);
        }, 3);
    }
}
