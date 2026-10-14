@extends('layouts.workspace')

@section('title') Invoice {{ $invoice->invoice_number }} @endsection
@section('crumb') Invoices / {{ $invoice->invoice_number }} @endsection

@section('content')
    @php
        $status = $summary['status'];
        $currency = $invoice->currency;
    @endphp
    <section class="heading">
        <div>
            <div class="eyebrow">Invoice</div>
            <h1>{{ $invoice->invoice_number }}</h1>
            <p class="sub">{{ $invoice->description ?: 'USDC payment request' }}</p>
        </div>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            @include('partials.pill', ['status' => $status])
            <a class="outline" href="{{ route('payment.show', $invoice->public_id) }}" target="_blank" rel="noopener">↗ <span>Open payment page</span></a>
            <a class="outline" href="{{ route('dashboard.invoices') }}">← <span>All invoices</span></a>
        </div>
    </section>

    @if($status === 'underpaid' || ($summary['partial'] && $summary['accepting']))
        <div class="callout warn" role="status">
            The customer has paid {{ \App\Support\Decimal6::display($summary['paid']) }} of {{ \App\Support\Decimal6::display($summary['amount']) }} {{ $currency }}.
            The remaining {{ \App\Support\Decimal6::display($summary['remaining']) }} {{ $currency }} can still be sent to the same address before the invoice expires.
        </div>
    @elseif($status === 'expired' && $summary['has_paid'])
        <div class="callout bad" role="status">
            This invoice expired with a partial payment of {{ \App\Support\Decimal6::display($summary['paid']) }} {{ $currency }}.
            Decide whether to refund it or credit the customer. Transfers that arrive after expiry appear under <a href="{{ route('dashboard.review') }}">Needs review</a>.
        </div>
    @elseif($status === 'overpaid')
        <div class="callout warn" role="status">
            The customer sent {{ \App\Support\Decimal6::display($summary['excess']) }} {{ $currency }} more than requested. The invoice is not marked paid automatically, so decide how to handle the difference.
        </div>
    @elseif($status === 'paid')
        <div class="callout good" role="status">Paid in full{{ $invoice->paid_at ? ' on ' . $invoice->paid_at->format('M j, Y · H:i') . ' UTC' : '' }}.</div>
    @elseif($status === 'expired')
        <div class="callout" role="status">This invoice expired before any payment was recorded.</div>
    @elseif($status === 'cancelled')
        <div class="callout" role="status">This invoice was cancelled.</div>
    @endif

    <section class="detail-grid">
        <article class="panel">
            <div class="panel-head"><div><h2>Payment progress</h2><p>Totals include every transfer that counts toward this invoice.</p></div></div>
            <div class="totals">
                <div><div class="lbl">Invoice amount</div><div class="val">{{ \App\Support\Decimal6::display($summary['amount']) }} <small>{{ $currency }}</small></div></div>
                <div><div class="lbl">Paid so far</div><div class="val">{{ \App\Support\Decimal6::display($summary['paid']) }} <small>{{ $currency }}</small></div></div>
                @if($summary['excess'] !== '0.000000')
                    <div><div class="lbl">Over by</div><div class="val">{{ \App\Support\Decimal6::display($summary['excess']) }} <small>{{ $currency }}</small></div></div>
                @else
                    <div><div class="lbl">Remaining</div><div class="val">{{ \App\Support\Decimal6::display($summary['remaining']) }} <small>{{ $currency }}</small></div></div>
                @endif
            </div>
            <div class="progress-wrap">
                <div class="bar {{ $summary['partial'] ? 'warn' : '' }}" role="img" aria-label="{{ $summary['percent'] }} percent paid"><i style="width:{{ $summary['percent'] }}%"></i></div>
            </div>
        </article>

        <article class="panel">
            <div class="panel-head"><div><h2>Details</h2></div></div>
            <dl class="kv">
                <dt>Status</dt><dd>@include('partials.pill', ['status' => $status])</dd>
                <dt>Network</dt><dd>{{ $invoice->network }}</dd>
                <dt>Payment address</dt><dd class="mono">{{ $invoice->payment_address }}</dd>
                <dt>Created</dt><dd>{{ $invoice->created_at?->format('M j, Y · H:i') }} UTC</dd>
                <dt>Expires</dt><dd>{{ $invoice->expires_at ? $invoice->expires_at->format('M j, Y · H:i') . ' UTC' : '—' }}</dd>
                @if($invoice->paid_at)<dt>Paid</dt><dd>{{ $invoice->paid_at->format('M j, Y · H:i') }} UTC</dd>@endif
                @if($invoice->external_id)<dt>Your reference</dt><dd>{{ $invoice->external_id }}</dd>@endif
            </dl>
        </article>
    </section>

    <section class="panel section-gap">
        <div class="panel-head"><div><h2>Payments on this invoice</h2><p>Every transfer recorded against it, newest first.</p></div><span class="count">{{ $invoice->payments->count() }} recorded</span></div>
        @if($invoice->payments->isNotEmpty())
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Received</th><th>Amount</th><th>Status</th><th>Confirmations</th><th>Transaction</th></tr></thead>
                    <tbody>
                    @foreach($invoice->payments as $payment)
                        @php $txUrl = \App\Support\Explorer::txUrl($payment->transaction_hash); @endphp
                        <tr>
                            <td class="muted">{{ ($payment->detected_at ?? $payment->created_at)?->format('M j, Y · H:i') }}</td>
                            <td class="amount">{{ \App\Support\Decimal6::display($payment->amount) }} <span class="muted">{{ $payment->currency }}</span></td>
                            <td>@include('partials.pill', ['status' => $payment->status])</td>
                            <td class="muted">{{ $payment->confirmations }}</td>
                            <td class="mono">
                                @if($txUrl)<a class="link" href="{{ $txUrl }}" target="_blank" rel="noopener">{{ \App\Support\Explorer::shortHash($payment->transaction_hash) }} ↗</a>@else{{ \App\Support\Explorer::shortHash($payment->transaction_hash) }}@endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <p class="note">Transfers marked “Needs review” arrived after the invoice expired or was cancelled, so they are not counted toward the total above.</p>
        @else
            <div class="empty"><div class="empty-mark">⌁</div><h3>No payment yet.</h3><p>Transfers to this invoice's address will appear here as soon as they are detected.</p></div>
        @endif
    </section>
@endsection
