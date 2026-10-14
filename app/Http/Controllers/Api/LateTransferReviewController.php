<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Read-only merchant-scoped access to payment events requiring manual review. */
class LateTransferReviewController extends Controller
{
    public function index(Request $request)
    {
        $merchant = $request->attributes->get('merchant') ?? abort(401);
        $network = (string) config('arc.network');
        $invoiceIds = $merchant->invoices()->where('network', $network)->pluck('id')->map(fn ($id) => (int) $id)->all();

        $payments = Payment::query()
            ->with('invoice:id,public_id,invoice_number,amount,currency,status,expires_at')
            ->where('network', $network)
            ->where('status', 'late_review')
            ->whereHas('invoice', fn ($query) => $query->where('merchant_id', $merchant->id))
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->map(fn (Payment $payment) => [
                'type' => 'payment',
                'id' => $payment->id,
                'invoice_id' => $payment->invoice?->public_id,
                'invoice_number' => $payment->invoice?->invoice_number,
                'transaction_hash' => $payment->transaction_hash,
                'log_index' => $payment->log_index,
                'token_contract' => $payment->token_contract,
                'from_address' => $payment->from_address,
                'to_address' => $payment->to_address,
                'amount' => (string) $payment->amount,
                'currency' => $payment->currency,
                'block_number' => $payment->block_number,
                'confirmations' => $payment->confirmations,
                'status' => 'needs_manual_review',
                'created_at' => $payment->created_at?->toISOString(),
            ])->values();

        $legacyReviews = collect();
        if ($invoiceIds !== [] && DB::getSchemaBuilder()->hasTable('legacy_transfer_reviews')) {
            $query = DB::table('legacy_transfer_reviews')->where('network', $network)
                ->where(function ($query) use ($invoiceIds) {
                    foreach ($invoiceIds as $id) {
                        $query->orWhereJsonContains('candidate_invoice_ids', $id);
                    }
                })
                ->orderBy('id')->limit(100);

            $legacyReviews = $query->get()->map(function ($review) use ($invoiceIds) {
                $candidateIds = json_decode($review->candidate_invoice_ids, true) ?: [];
                $ownedIds = array_values(array_intersect(array_map('intval', $candidateIds), $invoiceIds));
                return [
                    'type' => 'legacy_transfer',
                    'id' => $review->id,
                    'transaction_hash' => $review->transaction_hash,
                    'log_index' => $review->log_index,
                    'token_contract' => $review->token_contract,
                    'from_address' => $review->from_address,
                    'to_address' => $review->to_address,
                    'amount' => (string) $review->amount,
                    'block_number' => $review->block_number,
                    'confirmations' => $review->confirmations,
                    'candidate_invoice_ids' => $ownedIds,
                    'review_reason' => $review->review_reason,
                    'status' => 'needs_manual_review',
                    'created_at' => $review->created_at,
                ];
            })->values();
        }

        return response()->json([
            'data' => [
                'late_payments' => $payments,
                'legacy_transfers' => $legacyReviews,
            ],
            'meta' => ['read_only' => true, 'limit_per_type' => 100],
        ]);
    }
}
