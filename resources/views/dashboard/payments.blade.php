@extends('layouts.workspace')

@section('title', 'Payments')
@section('crumb', 'Payments')

@section('content')
@if(!$merchant)
    @include('partials.no-merchant')
@else
    <section class="heading">
        <div>
            <div class="eyebrow">Payments</div>
            <h1>Every transfer received.</h1>
            <p class="sub">Newest first, across all of your invoices.</p>
        </div>
    </section>

    <section class="panel">
        @if($payments->count() > 0)
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Received</th><th>Invoice</th><th>Amount</th><th>Status</th><th>Confirmations</th><th>Transaction</th></tr></thead>
                    <tbody>
                    @foreach($payments as $payment)
                        @php $txUrl = \App\Support\Explorer::txUrl($payment->transaction_hash); @endphp
                        <tr>
                            <td class="muted">{{ ($payment->detected_at ?? $payment->created_at)?->format('M j, Y · H:i') }}</td>
                            <td>
                                @if($payment->invoice)
                                    <a class="link invoice-id" href="{{ route('dashboard.invoice', $payment->invoice->public_id) }}">{{ $payment->invoice->invoice_number }}</a>
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
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
            @include('partials.pager', ['paginator' => $payments])
        @else
            <div class="empty"><div class="empty-mark">⌁</div><h3>No payments yet.</h3><p>Transfers sent to your invoice addresses will appear here once they are detected on-chain.</p></div>
        @endif
    </section>
@endif
@endsection
