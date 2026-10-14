<?php

namespace App\Services;

use App\Models\Invoice;
use App\Support\Decimal6;

/**
 * Read-only view of how much of an invoice has been paid.
 * Uses the same payment statuses the matcher counts, so the UI always agrees with settlement logic.
 */
class InvoiceBalance
{
    /** Payment statuses that count toward an invoice total (mirrors PaymentMatcher). */
    public const COUNTED = ['confirming', 'confirmed', 'underpaid', 'overpaid'];

    /** Invoice statuses that become "expired" for display once expires_at has passed. */
    private const EXPIRES_WHEN_UNSETTLED = ['pending', 'detected', 'underpaid'];

    /** Display states in which the invoice can still receive payment. */
    private const ACCEPTING = ['pending', 'detected', 'confirming', 'underpaid'];

    /**
     * @return array{amount:string,paid:string,remaining:string,excess:string,percent:int,status:string,raw_status:string,expired:bool,has_paid:bool,partial:bool,accepting:bool}
     */
    public function summarize(Invoice $invoice): array
    {
        $amount = Decimal6::normalize($invoice->amount);

        $paid = '0.000000';
        foreach ($invoice->payments as $payment) {
            if (in_array($payment->status, self::COUNTED, true)) {
                $paid = Decimal6::add($paid, Decimal6::normalize($payment->amount));
            }
        }

        $rawStatus = (string) $invoice->status;
        $expired = $rawStatus === 'expired'
            || (
                $invoice->expires_at !== null
                && $invoice->expires_at->isPast()
                && in_array($rawStatus, self::EXPIRES_WHEN_UNSETTLED, true)
            );
        $status = $expired ? 'expired' : $rawStatus;

        $hasPaid = Decimal6::cmp($paid, '0') > 0;

        return [
            'amount' => $amount,
            'paid' => $paid,
            'remaining' => Decimal6::sub($amount, $paid),
            'excess' => Decimal6::sub($paid, $amount),
            'percent' => Decimal6::percent($paid, $amount),
            'status' => $status,
            'raw_status' => $rawStatus,
            'expired' => $expired,
            'has_paid' => $hasPaid,
            'partial' => $hasPaid && Decimal6::cmp($paid, $amount) < 0,
            'accepting' => in_array($status, self::ACCEPTING, true),
        ];
    }
}
