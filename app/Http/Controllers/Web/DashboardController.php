<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\Invoice;
use App\Models\Wallet;
use App\Services\DashboardReviewQueue;
use App\Services\InvoiceBalance;
use App\Services\InvoiceAddressAllocator;
use App\Services\InvoiceAddressReservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    private const FILTERS = ['all', 'open', 'partial', 'paid', 'overpaid', 'expired', 'cancelled'];

    private function merchant(Request $request): ?Merchant
    {
        return $request->user()->merchants()->first();
    }

    /** Invoices that can still receive a payment right now. */
    private function awaiting($query)
    {
        return $query->where(function ($outer) {
            $outer->where('status', 'confirming')
                ->orWhere(function ($inner) {
                    $inner->whereIn('status', ['pending', 'detected', 'underpaid'])
                        ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
                });
        });
    }

    private function applyFilter($query, string $filter)
    {
        switch ($filter) {
            case 'open':
                return $this->awaiting($query);
            case 'partial':
                return $query->where('status', 'underpaid')
                    ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
            case 'paid':
                return $query->where('status', 'paid');
            case 'overpaid':
                return $query->where('status', 'overpaid');
            case 'cancelled':
                return $query->where('status', 'cancelled');
            case 'expired':
                return $query->where(function ($outer) {
                    $outer->where('status', 'expired')
                        ->orWhere(function ($inner) {
                            $inner->whereIn('status', ['pending', 'detected', 'underpaid'])
                                ->whereNotNull('expires_at')
                                ->where('expires_at', '<=', now());
                        });
                });
            default:
                return $query;
        }
    }

    public function overview(Request $request, InvoiceBalance $balance, DashboardReviewQueue $queue)
    {
        $merchant = $this->merchant($request);

        if (!$merchant) {
            return view('dashboard', ['merchant' => null]);
        }

        $review = $queue->forMerchant($merchant);
        $overpaid = $merchant->invoices()->where('status', 'overpaid')->count();

        $metrics = [
            'awaiting' => $this->awaiting($merchant->invoices())->count(),
            'partial' => $this->applyFilter($merchant->invoices(), 'partial')->count(),
            'paid' => $merchant->invoices()->where('status', 'paid')->count(),
            'review' => $overpaid + $review['late_payments']->count() + $review['legacy_transfers']->count(),
            'total' => $merchant->invoices()->count(),
        ];

        $recent = $merchant->invoices()
            ->with(['payments' => fn ($q) => $q->whereIn('status', InvoiceBalance::COUNTED)])
            ->latest('id')
            ->limit(8)
            ->get();

        $balances = $recent->mapWithKeys(fn ($invoice) => [$invoice->id => $balance->summarize($invoice)]);

        return view('dashboard', compact('merchant', 'metrics', 'recent', 'balances'));
    }

    public function invoices(Request $request, InvoiceBalance $balance)
    {
        $merchant = $this->merchant($request);
        $filter = in_array($request->query('filter'), self::FILTERS, true) ? (string) $request->query('filter') : 'all';

        if (!$merchant) {
            return view('dashboard.invoices', ['merchant' => null, 'filter' => $filter, 'filters' => self::FILTERS]);
        }

        $query = $merchant->invoices()
            ->with(['payments' => fn ($q) => $q->whereIn('status', InvoiceBalance::COUNTED)])
            ->latest('id');

        $invoices = $this->applyFilter($query, $filter)->simplePaginate(20)->withQueryString();
        $balances = $invoices->getCollection()->mapWithKeys(fn ($invoice) => [$invoice->id => $balance->summarize($invoice)]);
        $filters = self::FILTERS;

        return view('dashboard.invoices', compact('merchant', 'invoices', 'balances', 'filter', 'filters'));
    }

    private function activeMerchantOrAbort(Request $request): Merchant
    {
        $merchant = $this->merchant($request);

        abort_unless($merchant && $merchant->status === 'active', 403);

        return $merchant;
    }

    private function eligibleInvoiceWallets(Merchant $merchant, string $network)
    {
        return $merchant->wallets()
            ->where('network', $network)
            ->where('is_active', true)
            ->get()
            ->filter(function (Wallet $wallet) use ($network) {
                $address = strtolower(trim($wallet->address));

                $alreadyInvoiced = Invoice::query()
                    ->where('network', $network)
                    ->whereRaw('LOWER(payment_address) = ?', [$address])
                    ->exists();

                $alreadyReserved = DB::table('invoice_address_reservations')
                    ->where('network', $network)
                    ->where('normalized_address', $address)
                    ->exists();

                return ! $alreadyInvoiced && ! $alreadyReserved;
            })
            ->values();
    }

    public function createInvoice(Request $request, InvoiceAddressAllocator $allocator)
    {
        $merchant = $this->activeMerchantOrAbort($request);
        $network = (string) config('arc.network');
        $hdEnabled = $allocator->hasActiveConfig((int) $merchant->id, $network);
        $wallets = $hdEnabled
            ? collect()
            : $this->eligibleInvoiceWallets($merchant, $network);

        return view('dashboard.invoice-create', compact(
            'merchant', 'network', 'hdEnabled', 'wallets'
        ));
    }

    public function storeInvoice(
        Request $request,
        InvoiceAddressAllocator $allocator,
        InvoiceAddressReservation $reservations
    )
    {
        $merchant = $this->activeMerchantOrAbort($request);
        $network = (string) config('arc.network');
        $hdEnabled = $allocator->hasActiveConfig((int) $merchant->id, $network);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'regex:/^\d{1,24}(\.\d{1,6})?$/'],
            'description' => ['nullable', 'string', 'max:1000'],
            'external_id' => ['nullable', 'string', 'max:255'],
            'expires_in_minutes' => ['required', 'integer', 'min:1', 'max:10080'],
            'wallet_id' => $hdEnabled
                ? ['prohibited']
                : ['nullable', 'integer'],
            'payment_address' => $hdEnabled
                ? ['prohibited']
                : ['nullable', 'string', 'regex:/^0x[a-fA-F0-9]{40}$/'],
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
                'expires_at' => now()->addMinutes((int) $data['expires_in_minutes']),
            ]);
        };

        if ($hdEnabled) {
            $invoice = $allocator->createWithDerivedAddress(
                $merchant, $network, $createInvoice
            );

            if (! $invoice) {
                return back()->withInput()->withErrors([
                    'payment_address' => 'Automatic address allocation is unavailable. Please try again.',
                ]);
            }
        } else {
            $wallets = $this->eligibleInvoiceWallets($merchant, $network);

            if ($wallets->isNotEmpty()) {
                $request->validate([
                    'wallet_id' => ['required', 'integer'],
                    'payment_address' => ['prohibited'],
                ]);

                $wallet = $wallets->firstWhere('id', (int) $data['wallet_id']);

                if (! $wallet) {
                    return back()->withInput()->withErrors([
                        'wallet_id' => 'Choose an eligible active wallet that has not been used for an invoice.',
                    ]);
                }

                $address = strtolower($wallet->address);
            } else {
                $request->validate([
                    'wallet_id' => ['prohibited'],
                    'payment_address' => ['required', 'string', 'regex:/^0x[a-fA-F0-9]{40}$/'],
                ]);

                $address = strtolower($data['payment_address']);
            }

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
                return back()->withInput()->withErrors([
                    'payment_address' => 'That address has already been assigned to an invoice on this network. Use a new address.',
                ]);
            }
        }

        return redirect()
            ->route('dashboard.invoice', $invoice->public_id)
            ->with('status', 'invoice-created');
    }

    public function invoice(Request $request, string $publicId, InvoiceBalance $balance)
    {
        $merchant = $this->merchant($request) ?? abort(404);

        $invoice = $merchant->invoices()
            ->where('public_id', $publicId)
            ->with(['payments' => fn ($q) => $q->latest('id')])
            ->firstOrFail();

        $summary = $balance->summarize($invoice);

        return view('dashboard.invoice', compact('merchant', 'invoice', 'summary'));
    }

    public function payments(Request $request)
    {
        $merchant = $this->merchant($request);

        if (!$merchant) {
            return view('dashboard.payments', ['merchant' => null]);
        }

        $payments = Payment::query()
            ->with('invoice:id,public_id,invoice_number,currency')
            ->whereIn('invoice_id', $merchant->invoices()->select('id'))
            ->latest('id')
            ->simplePaginate(25)
            ->withQueryString();

        return view('dashboard.payments', compact('merchant', 'payments'));
    }

    public function review(Request $request, DashboardReviewQueue $queue)
    {
        $merchant = $this->merchant($request);

        if (!$merchant) {
            return view('dashboard.review', ['merchant' => null]);
        }

        $review = $queue->forMerchant($merchant);
        $overpaidInvoices = $merchant->invoices()
            ->with(['payments' => fn ($q) => $q->whereIn('status', InvoiceBalance::COUNTED)])
            ->where('status', 'overpaid')
            ->latest('id')
            ->limit(100)
            ->get();

        $balances = $overpaidInvoices->mapWithKeys(
            fn ($invoice) => [$invoice->id => app(InvoiceBalance::class)->summarize($invoice)]
        );

        return view('dashboard.review', [
            'merchant' => $merchant,
            'latePayments' => $review['late_payments'],
            'legacyTransfers' => $review['legacy_transfers'],
            'overpaidInvoices' => $overpaidInvoices,
            'balances' => $balances,
        ]);
    }
}
