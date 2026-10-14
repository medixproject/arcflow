@extends('layouts.workspace')

@section('title', 'Overview')
@section('crumb', 'Overview')

@section('content')
@if(!$merchant)
    @include('partials.no-merchant')
@else
    <section class="heading">
        <div>
            <div class="eyebrow">Merchant workspace</div>
            <h1>Good to see you, {{ $merchant->name }}.</h1>
            <p class="sub">A clear view of your invoice and payment activity.</p>
        </div>
        <a class="outline" href="/">↖ <span>View public site</span></a>
    </section>

    <section class="metrics four" aria-label="Invoice overview">
        <a class="metric" href="{{ route('dashboard.invoices', ['filter' => 'open']) }}">
            <div class="metric-top"><span>Awaiting payment</span><span class="metric-icon">◷</span></div>
            <div class="metric-value">{{ number_format($metrics['awaiting']) }}</div>
            <div class="metric-note"><b>{{ number_format($metrics['partial']) }}</b> partially paid</div>
        </a>
        <a class="metric" href="{{ route('dashboard.invoices', ['filter' => 'paid']) }}">
            <div class="metric-top"><span>Paid</span><span class="metric-icon">✓</span></div>
            <div class="metric-value">{{ number_format($metrics['paid']) }}</div>
            <div class="metric-note">Fully settled invoices</div>
        </a>
        <a class="metric" href="{{ route('dashboard.review') }}">
            <div class="metric-top"><span>Needs review</span><span class="metric-icon">⚑</span></div>
            <div class="metric-value">{{ number_format($metrics['review']) }}</div>
            <div class="metric-note">
                @if($metrics['review'] > 0)<span class="warn">Action needed</span>@else Nothing waiting @endif
            </div>
        </a>
        <a class="metric" href="{{ route('dashboard.invoices') }}">
            <div class="metric-top"><span>All invoices</span><span class="metric-icon">▤</span></div>
            <div class="metric-value">{{ number_format($metrics['total']) }}</div>
            <div class="metric-note">Since you started</div>
        </a>
    </section>

    @if($metrics['review'] > 0)
        <div class="callout warn" role="status">
            {{ number_format($metrics['review']) }} item(s) need a manual decision, such as a payment that arrived after an invoice expired or an overpayment.
            <a href="{{ route('dashboard.review') }}">Review them</a>.
        </div>
    @endif

    <section class="panel" id="invoices">
        <div class="panel-head">
            <div><h2>Recent invoices</h2><p>Your latest payment requests, including how much has been paid.</p></div>
            <a class="count" href="{{ route('dashboard.invoices') }}">View all →</a>
        </div>
        @if($recent->isNotEmpty())
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Invoice</th><th>Amount</th><th>Paid</th><th>Status</th><th>Created</th></tr></thead>
                    <tbody>
                    @foreach($recent as $invoice)
                        @php $b = $balances[$invoice->id]; @endphp
                        <tr>
                            <td>
                                <a class="link invoice-id" href="{{ route('dashboard.invoice', $invoice->public_id) }}">{{ $invoice->invoice_number }}</a><br>
                                <span class="muted" style="font-size:10px">{{ $invoice->description ?: 'USDC payment request' }}</span>
                            </td>
                            <td class="amount">{{ \App\Support\Decimal6::display($b['amount']) }} <span class="muted">{{ $invoice->currency }}</span></td>
                            <td>@include('partials.paid-cell', ['b' => $b])</td>
                            <td>@include('partials.pill', ['status' => $b['status']])</td>
                            <td class="muted">{{ $invoice->created_at?->format('M j, Y · H:i') }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="empty"><div class="empty-mark">▤</div><h3>Your invoice activity starts here.</h3><p>When invoices are created for this merchant, their latest statuses will appear in this workspace.</p></div>
        @endif
    </section>

    <section class="bottom-grid">
        <article class="info-card"><div class="icon">⌁</div><h3>Partial payments add up</h3><p>If a customer pays in several transfers, Arcflow adds them together. The invoice is marked paid once the total matches, and anything beyond it appears under Needs review.</p></article>
        <article class="info-card"><div class="icon">◇</div><h3>Built for integration</h3><p>Keep using your API and webhooks. This workspace is a read-only view of the same invoices and payments.</p></article>
    </section>
@endif
@endsection
