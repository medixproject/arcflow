@extends('layouts.workspace')

@section('title', 'Invoices')
@section('crumb', 'Invoices')

@section('content')
@if(!$merchant)
    @include('partials.no-merchant')
@else
    @php
        $filterLabels = [
            'all' => 'All', 'open' => 'Awaiting payment', 'partial' => 'Partially paid', 'paid' => 'Paid',
            'overpaid' => 'Overpaid', 'expired' => 'Expired', 'cancelled' => 'Cancelled',
        ];
    @endphp
    <section class="heading">
        <div>
            <div class="eyebrow">Invoices</div>
            <h1>Every payment request.</h1>
            <p class="sub">Newest first. Select an invoice to see its payments.</p>
        </div>
        @if($merchant->status === 'active')
            <a class="outline" href="{{ route('dashboard.invoices.create') }}">+ Create invoice</a>
        @endif
    </section>

    <section class="panel">
        <div class="filters" role="navigation" aria-label="Filter invoices">
            @foreach($filters as $f)
                <a class="{{ $filter === $f ? 'active' : '' }}" href="{{ $f === 'all' ? route('dashboard.invoices') : route('dashboard.invoices', ['filter' => $f]) }}">{{ $filterLabels[$f] }}</a>
            @endforeach
        </div>
        @if($invoices->count() > 0)
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Invoice</th><th>Amount</th><th>Paid</th><th>Status</th><th>Expires</th><th>Created</th></tr></thead>
                    <tbody>
                    @foreach($invoices as $invoice)
                        @php $b = $balances[$invoice->id]; @endphp
                        <tr>
                            <td>
                                <a class="link invoice-id" href="{{ route('dashboard.invoice', $invoice->public_id) }}">{{ $invoice->invoice_number }}</a><br>
                                <span class="muted" style="font-size:10px">{{ $invoice->description ?: 'USDC payment request' }}</span>
                            </td>
                            <td class="amount">{{ \App\Support\Decimal6::display($b['amount']) }} <span class="muted">{{ $invoice->currency }}</span></td>
                            <td>@include('partials.paid-cell', ['b' => $b])</td>
                            <td>@include('partials.pill', ['status' => $b['status']])</td>
                            <td class="muted">{{ $invoice->expires_at?->format('M j, H:i') ?? '—' }}</td>
                            <td class="muted">{{ $invoice->created_at?->format('M j, Y · H:i') }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @include('partials.pager', ['paginator' => $invoices])
        @else
            <div class="empty"><div class="empty-mark">▤</div><h3>No invoices match this view.</h3><p>Try another filter, or create an invoice through the API.</p></div>
        @endif
    </section>
@endif
@endsection
