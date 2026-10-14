<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f3ed">
    <meta name="robots" content="noindex, nofollow">
    @php
        $state = $balance['status'];
        $accepting = $balance['accepting'];
        $currency = $invoice->currency;
        $headline = match (true) {
            $state === 'paid' => 'Payment complete',
            $state === 'expired' => 'This payment request has expired',
            $state === 'cancelled' => 'This payment request was cancelled',
            $state === 'overpaid' => 'Payment received',
            $state === 'underpaid' => 'Partial payment received',
            in_array($state, ['detected', 'confirming'], true) => 'Confirming your payment',
            default => 'Complete your payment',
        };
        $subline = match (true) {
            $state === 'paid' => 'Thank you. Your payment has been confirmed.',
            $state === 'expired', $state === 'cancelled' => 'Please do not send funds for this request.',
            $state === 'overpaid' => 'We received more than the requested amount.',
            $state === 'underpaid' => 'Send the remaining amount to finish this payment.',
            in_array($state, ['detected', 'confirming'], true) => 'Your transfer was detected. This page updates automatically.',
            default => 'Send the exact amount below. This page updates automatically.',
        };
    @endphp
    @if($accepting)<meta http-equiv="refresh" content="5">@endif
    <title>{{ $headline }} — Arcflow</title>
    <style>
        :root{--paper:#f5f3ed;--surface:#fffefa;--ink:#202a2c;--muted:#707a7b;--line:#e4e4dc;--green:#08765f;--mint:#e5f2ec;--serif:Georgia,'Times New Roman',serif;--sans:Inter,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif}*{box-sizing:border-box}body{margin:0;background:var(--paper);color:var(--ink);font-family:var(--sans);-webkit-font-smoothing:antialiased}.shell{max-width:980px;margin:auto;padding:0 24px}.top{height:78px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--line)}.brand{display:flex;align-items:center;gap:10px;font-family:var(--serif);font-weight:700;font-size:23px;letter-spacing:-.06em}.mark{width:29px;height:29px;display:grid;place-items:center;border:1px solid var(--green);color:var(--green);border-radius:8px;font-family:var(--sans);font-size:15px}.top-note{font-size:10px;color:var(--muted)}main{max-width:610px;margin:44px auto 70px}.eyebrow{font-size:10px;letter-spacing:.16em;text-transform:uppercase;font-weight:750;color:var(--green)}.box{background:var(--surface);border:1px solid var(--line);border-radius:13px;overflow:hidden;box-shadow:0 18px 60px rgba(32,42,44,.04)}.box-head{padding:28px 30px 24px;border-bottom:1px solid var(--line)}h1{font-family:var(--serif);font-size:clamp(28px,5vw,38px);font-weight:400;letter-spacing:-.05em;line-height:1.08;margin:14px 0}.sub{font-size:12px;line-height:1.7;color:var(--muted);margin:0}.amount-area{padding:28px 30px;background:#faf9f4;border-bottom:1px solid var(--line)}.amount-label{font-size:10px;color:var(--muted);margin-bottom:8px}.amount{font-family:var(--serif);font-size:42px;letter-spacing:-.05em;overflow-wrap:anywhere}.currency{font-family:var(--sans);font-size:13px;color:var(--muted);letter-spacing:0}.status-row{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:16px}.status{display:inline-flex;align-items:center;gap:7px;border-radius:99px;padding:7px 10px;background:#f0eee6;color:#626b69;font-size:10px;font-weight:750;text-transform:capitalize}.status:before{content:'';height:6px;width:6px;border-radius:50%;background:#87918e}.status.paid{background:#e5f2ec;color:#17694f}.status.paid:before{background:#16815f}.status.detected,.status.confirming,.status.pending{background:#fff1d9;color:#8b5b12}.status.detected:before,.status.confirming:before,.status.pending:before{background:#d89b2c}.details{padding:26px 30px}.field-label{display:block;font-size:10px;color:var(--muted);margin-bottom:9px}.address{padding:15px;border:1px solid #dcdfd7;background:#f8f7f1;border-radius:8px;font:12px/1.7 ui-monospace,SFMono-Regular,Menlo,monospace;word-break:break-all}.meta-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:22px}.meta-value{font-size:12px;font-weight:650;overflow-wrap:anywhere}.divider{height:1px;background:var(--line);margin:24px 0}.payment-evidence{padding:17px;border:1px solid var(--line);border-radius:8px}.tx{font:11px/1.7 ui-monospace,SFMono-Regular,Menlo,monospace;word-break:break-all}.button{display:inline-flex;align-items:center;justify-content:center;padding:12px 15px;margin-top:15px;border-radius:7px;background:var(--green);color:white;font-size:11px;font-weight:700;text-decoration:none}.footnote{font-size:10px;line-height:1.8;color:var(--muted);margin:18px 0 0}.bottom{display:flex;justify-content:space-between;gap:12px;margin-top:20px;font-size:9px;color:#87908e}
        @media(max-width:520px){.shell{padding:0 14px}.top{height:68px}.brand{font-size:21px}.top-note{font-size:9px}main{margin:25px auto 45px}.box-head,.amount-area,.details{padding:22px 19px}.amount{font-size:37px}.meta-grid{gap:14px}.bottom{flex-direction:column}}

        .status.underpaid{background:#fff1d9;color:#8b5b12}.status.underpaid:before{background:#d89b2c}.status.confirmed{background:#e5f2ec;color:#17694f}.status.confirmed:before{background:#16815f}.status.overpaid{background:#e6eef8;color:#2b5a8c}.status.overpaid:before{background:#4a7fb5}.status.expired,.status.cancelled,.status.late_review{background:#eceae2;color:#6b7473}.status.expired:before,.status.cancelled:before,.status.late_review:before{background:#98a19e}
        .totals-line{display:flex;justify-content:space-between;gap:12px;font-size:12px;margin-top:14px;color:var(--muted)}.totals-line b{color:var(--ink);font-variant-numeric:tabular-nums}.bar{height:7px;border-radius:99px;background:#ece9df;overflow:hidden;margin-top:12px}.bar>i{display:block;height:100%;background:#d89b2c;border-radius:99px}
        .callout{margin:22px 30px 0;padding:15px 17px;border-radius:9px;font-size:12px;line-height:1.7;border:1px solid var(--line);background:#fbfaf6}.callout strong{font-weight:750}.callout.warn{background:#fff6e6;border-color:#f0dcae;color:#7d5410}.callout.bad{background:#fbece7;border-color:#efcdc2;color:#8c4535}.callout.good{background:#e8f4ed;border-color:#c9e3d3;color:#17694f}
        .evidence-list{display:grid;gap:10px}.evidence-row{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap}.evidence-amount{font-weight:700;font-variant-numeric:tabular-nums;font-size:13px}.evidence-meta{font-size:10px;color:var(--muted);margin-top:4px}.tx a{color:var(--green);text-decoration:underline}
        @media(max-width:520px){.callout{margin:18px 19px 0}}
    </style>
</head>
<body>
<div class="shell">
    <header class="top">
        <a class="brand" href="/" style="text-decoration:none;color:inherit"><span class="mark">A</span> arcflow</a>
        <span class="top-note">Secure USDC payment</span>
    </header>
    <main>
        <div class="eyebrow">Payment request · {{ $invoice->invoice_number }}</div>
        <div class="box" style="margin-top:14px">
            <div class="box-head">
                <h1>{{ $headline }}</h1>
                <p class="sub">{{ $subline }}</p>
            </div>

            <div class="amount-area">
                <div class="amount-label">Amount due</div>
                <div class="amount">{{ \App\Support\Decimal6::display($balance['amount']) }} <span class="currency">{{ $currency }}</span></div>
                @if($balance['has_paid'])
                    <div class="totals-line"><span>Received so far</span><b>{{ \App\Support\Decimal6::display($balance['paid']) }} {{ $currency }}</b></div>
                    @if($balance['partial'])
                        <div class="totals-line"><span>Remaining</span><b>{{ \App\Support\Decimal6::display($balance['remaining']) }} {{ $currency }}</b></div>
                        <div class="bar" role="img" aria-label="{{ $balance['percent'] }} percent received"><i style="width:{{ $balance['percent'] }}%"></i></div>
                    @elseif($balance['excess'] !== '0.000000')
                        <div class="totals-line"><span>Over the requested amount</span><b>{{ \App\Support\Decimal6::display($balance['excess']) }} {{ $currency }}</b></div>
                    @endif
                @endif
                <div class="status-row">
                    @include('partials.pill', ['status' => $state])
                    <span class="top-note">
                        @if($state === 'paid' && $invoice->paid_at)
                            Paid {{ $invoice->paid_at->copy()->utc()->format('M j, Y · H:i') }} UTC
                        @elseif($invoice->expires_at)
                            {{ $accepting ? 'Expires' : 'Expired' }} {{ $invoice->expires_at->copy()->utc()->format('M j, Y · H:i') }} UTC
                        @endif
                    </span>
                </div>
            </div>

            @if($state === 'underpaid' && $accepting)
                <div class="callout warn" role="status">We have received <strong>{{ \App\Support\Decimal6::display($balance['paid']) }} {{ $currency }}</strong> of <strong>{{ \App\Support\Decimal6::display($balance['amount']) }} {{ $currency }}</strong>. Send exactly <strong>{{ \App\Support\Decimal6::display($balance['remaining']) }} {{ $currency }}</strong> to the same address below before this request expires.</div>
            @elseif($accepting && in_array($state, ['detected', 'confirming'], true))
                <div class="callout" role="status">Payment detected. Waiting for network confirmation; you do not need to send anything else.</div>
            @elseif($accepting)
                <div class="callout" role="status">Send exactly <strong>{{ \App\Support\Decimal6::display($balance['amount']) }} {{ $currency }}</strong> to the address below, on the Arc network.</div>
            @elseif($state === 'expired')
                <div class="callout bad" role="alert"><strong>Do not send funds to this address.</strong> This request has expired and transfers made now are not credited automatically. Ask the merchant for a new payment request.@if($balance['has_paid']) A partial payment of {{ \App\Support\Decimal6::display($balance['paid']) }} {{ $currency }} was received before it expired, so please contact the merchant about it.@endif</div>
            @elseif($state === 'cancelled')
                <div class="callout bad" role="alert"><strong>Do not send funds to this address.</strong> The merchant cancelled this request. Ask them for a new one if you still need to pay.</div>
            @elseif($state === 'overpaid')
                <div class="callout warn" role="status">You sent {{ \App\Support\Decimal6::display($balance['excess']) }} {{ $currency }} more than requested. You do not need to send anything else; contact the merchant about the difference.</div>
            @elseif($state === 'paid')
                <div class="callout good" role="status">Your payment is complete. Keep the transaction reference below for your records.</div>
            @endif

            <div class="details">
                @if($accepting)
                    <span class="field-label">Send USDC to</span>
                    <div class="address">{{ $invoice->payment_address }}</div>
                @endif
                <div class="meta-grid" @if(!$accepting) style="margin-top:0" @endif>
                    <div><span class="field-label">Network</span><div class="meta-value">{{ $invoice->network }}</div></div>
                    <div><span class="field-label">Invoice</span><div class="meta-value">{{ $invoice->invoice_number }}</div></div>
                    @if($invoice->description)
                        <div style="grid-column:1/-1"><span class="field-label">For</span><div class="meta-value">{{ $invoice->description }}</div></div>
                    @endif
                </div>

                @if($payments->isNotEmpty())
                    <div class="divider"></div>
                    <span class="field-label">Payments received</span>
                    <div class="evidence-list">
                        @foreach($payments as $item)
                            @php $txUrl = \App\Support\Explorer::txUrl($item->transaction_hash); @endphp
                            <div class="payment-evidence">
                                <div class="evidence-row">
                                    <span class="evidence-amount">{{ \App\Support\Decimal6::display($item->amount) }} {{ $item->currency }}</span>
                                    @include('partials.pill', ['status' => $item->status])
                                </div>
                                <div class="evidence-meta">
                                    {{ $item->confirmations }} confirmation(s)
                                    @if($item->status === 'late_review') · received after this request ended, under merchant review @endif
                                </div>
                                <div class="tx" style="margin-top:8px">
                                    @if($txUrl)<a href="{{ $txUrl }}" target="_blank" rel="noopener">{{ $item->transaction_hash }}</a>@else{{ $item->transaction_hash }}@endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if($accepting)
                    <p class="footnote">Only send USDC on the Arc network to this address. Transfers on other networks or in other tokens cannot be recovered. You may pay in more than one transfer; they are added together.</p>
                @endif
            </div>
        </div>
        <div class="bottom"><span>Powered by Arcflow</span>@if($accepting)<span>Refreshing automatically</span>@endif</div>
    </main>
</div>
</body>
</html>
