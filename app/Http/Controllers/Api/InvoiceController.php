<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\InvoiceAddressAllocator;
use App\Services\InvoiceAddressReservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    private function merchant(Request $request)
    {
        return $request->attributes->get('merchant') ?? abort(401);
    }

    public function index(Request $request)
    {
        $merchant = $this->merchant($request);
        return response()->json(['data' => $merchant->invoices()->latest()->paginate(25)]);
    }

    public function store(
        Request $request,
        InvoiceAddressAllocator $allocator,
        InvoiceAddressReservation $reservations
    )
    {
        $merchant = $this->merchant($request);
        abort_unless($merchant->status === 'active', 403);
        $network = (string) config('arc.network');
        $hdEnabled = $allocator->hasActiveConfig((int) $merchant->id, $network);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'regex:/^\d{1,24}(\.\d{1,6})?$/'],
            'currency' => ['required', Rule::in(['USDC'])],
            'description' => ['nullable', 'string', 'max:1000'],
            'external_id' => ['nullable', 'string', 'max:255'],
            'expires_in_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
            // An active xpub is the only allowed address source for this merchant.
            'payment_address' => $hdEnabled
                ? ['prohibited']
                : ['required', 'string', 'regex:/^0x[a-fA-F0-9]{40}$/'],
        ]);

        $createInvoice = function (string $address) use ($merchant, $data, $network): Invoice {
            return $merchant->invoices()->create([
                'external_id' => $data['external_id'] ?? null,
                'amount' => $data['amount'],
                'currency' => 'USDC',
                'network' => $network,
                'payment_address' => strtolower($address),
                'description' => $data['description'] ?? null,
                'status' => 'pending',
                'expires_at' => now()->addMinutes($data['expires_in_minutes'] ?? 30),
            ]);
        };

        if ($hdEnabled) {
            $invoice = $allocator->createWithDerivedAddress($merchant, $network, $createInvoice);
            // Never fall back to a caller-provided address if a config disappears/races.
            if (!$invoice) {
                return response()->json(['message' => 'Automatic address allocation is temporarily unavailable.'], 409);
            }
        } else {
            $address = strtolower(trim($data['payment_address']));

            $invoice = DB::transaction(function () use (
                $network, $address, $createInvoice, $reservations
            ) {
                if (! $reservations->reserve($network, $address)) {
                    return null;
                }

                $invoice = $createInvoice($address);

                $reservations->associateInvoice(
                    $network, $address, (int) $invoice->id
                );

                return $invoice;
            }, 5);

            if (! $invoice) {
                return response()->json([
                    'message' => 'That address has already been assigned to an invoice on this network. Use a new address.',
                ], 409);
            }
        }

        return response()->json(['data' => $this->resource($invoice)], 201);
    }

    public function show(Request $request, Invoice $invoice)
    {
        abort_unless($invoice->merchant->user_id === $request->user()->id, 404);
        return response()->json(['data' => $this->resource($invoice)]);
    }

    public function cancel(Request $request, Invoice $invoice)
    {
        abort_unless($invoice->merchant->user_id === $request->user()->id, 404);
        abort_if(in_array($invoice->status, ['paid', 'cancelled'], true), 422);
        $invoice->update(['status' => 'cancelled']);
        return response()->json(['data' => $this->resource($invoice)]);
    }

    private function resource(Invoice $invoice): array
    {
        return [
            'id' => $invoice->public_id,
            'invoice_number' => $invoice->invoice_number,
            'status' => $invoice->status,
            'amount' => (string) $invoice->amount,
            'currency' => $invoice->currency,
            'network' => $invoice->network,
            'payment_address' => $invoice->payment_address,
            'payment_url' => route('payment.show', $invoice->public_id),
            'description' => $invoice->description,
            'expires_at' => $invoice->expires_at?->toISOString(),
            'paid_at' => $invoice->paid_at?->toISOString(),
        ];
    }
}
