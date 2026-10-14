@extends('layouts.workspace')

@section('title', 'Needs review')
@section('crumb', 'Needs review')

@section('content')
@if(!$merchant)
    @include('partials.no-merchant')
@else
    <section class="heading">
        <div>
            <div class="eyebrow">Needs review</div>
            <h1>Transfers that need a decision.</h1>
            <p class="sub">Arcflow never marks these as paid on its own. Review each one and decide whether to refund or credit it.</p>
        </div>
    </section>

    @if($overpaidInvoices->isEmpty() && $latePayments->isEmpty() && $legacyTransfers->isEmpty())
        <section class="panel">
            <div class="empty"><div class="empty-mark">✓</div><h3>Nothing needs review.</h3><p>Late payments, overpayments and transfers to old addresses will be listed here when they happen.</p></div>
        </section>
    @endif

    @if($overpaidInvoices->isNotEmpty())
        <section class="panel section-gap">
            <div class="panel-head"><div><h2>Overpaid invoices</h2><p>The customer sent more than the invoice amount.</p></div><span class="count">{{ $overpaidInvoices->count() }}</span></div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Invoice</th><th>Invoice amount</th><th>Received</th><th>Over by</th></tr></thead>
                    <tbody>
                    @foreach($overpaidInvoices as $invoice)
                        @php $b = $balances[$invoice->id]; @endphp
                        <tr>
                            <td><a class="link invoice-id" href="{{ route('dashboard.invoice', $invoice->public_id) }}">{{ $invoice->invoice_number }}</a></td>
                            <td class="amount">{{ \App\Support\Decimal6::display($b['amount']) }} <span class="muted">{{ $invoice->currency }}</span></td>
                            <td class="amount">{{ \App\Support\Decimal6::display($b['paid']) }}</td>
                            <td class="amount">{{ \App\Support\Decimal6::display($b['excess']) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if($latePayments->isNotEmpty())
        <section class="panel section-gap">
            <div class="panel-head"><div><h2>Late payments</h2><p>Received after the invoice expired or was cancelled.</p></div><span class="count">{{ $latePayments->count() }}</span></div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Received</th><th>Invoice</th><th>Amount</th><th>From</th><th>Transaction</th></tr></thead>
                    <tbody>
                    @foreach($latePayments as $payment)
                        @php $txUrl = \App\Support\Explorer::txUrl($payment->transaction_hash); @endphp
                        <tr>
                            <td class="muted">{{ ($payment->detected_at ?? $payment->created_at)?->format('M j, Y · H:i') }}</td>
                            <td>
                                @if($payment->invoice)
                                    <a class="link invoice-id" href="{{ route('dashboard.invoice', $payment->invoice->public_id) }}">{{ $payment->invoice->invoice_number }}</a>
                                @else<span class="muted">—</span>@endif
                            </td>
                            <td class="amount">{{ \App\Support\Decimal6::display($payment->amount) }} <span class="muted">{{ $payment->currency }}</span></td>
                            <td class="mono">{{ \App\Support\Explorer::shortHash($payment->from_address) }}</td>
                            <td class="mono">
                                @if($txUrl)<a class="link" href="{{ $txUrl }}" target="_blank" rel="noopener">{{ \App\Support\Explorer::shortHash($payment->transaction_hash) }} ↗</a>@else{{ \App\Support\Explorer::shortHash($payment->transaction_hash) }}@endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if($legacyTransfers->isNotEmpty())
        <section class="panel section-gap">
            <div class="panel-head"><div><h2>Transfers to old invoice addresses</h2><p>Sent to an address from an earlier invoice that is no longer open.</p></div><span class="count">{{ $legacyTransfers->count() }}</span></div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Detected</th><th>Invoice(s)</th><th>Amount</th><th>From</th><th>Transaction</th><th>Reason</th></tr></thead>
                    <tbody>
                    @foreach($legacyTransfers as $row)
                        @php $txUrl = \App\Support\Explorer::txUrl($row->transaction_hash); @endphp
                        <tr>
                            <td class="muted">{{ $row->created_at ? \Illuminate\Support\Carbon::parse($row->created_at)->format('M j, Y · H:i') : '—' }}</td>
                            <td>
                                @forelse($row->invoices as $candidate)
                                    <a class="link invoice-id" href="{{ route('dashboard.invoice', $candidate->public_id) }}">{{ $candidate->invoice_number }}</a>@if(!$loop->last), @endif
                                @empty
                                    <span class="muted">—</span>
                                @endforelse
                            </td>
                            <td class="amount">{{ \App\Support\Decimal6::display($row->amount) }} <span class="muted">USDC</span></td>
                            <td class="mono">{{ \App\Support\Explorer::shortHash($row->from_address) }}</td>
                            <td class="mono">
                                @if($txUrl)<a class="link" href="{{ $txUrl }}" target="_blank" rel="noopener">{{ \App\Support\Explorer::shortHash($row->transaction_hash) }} ↗</a>@else{{ \App\Support\Explorer::shortHash($row->transaction_hash) }}@endif
                            </td>
                            <td class="muted">{{ str_replace('_', ' ', (string) $row->review_reason) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endif
@endsection
