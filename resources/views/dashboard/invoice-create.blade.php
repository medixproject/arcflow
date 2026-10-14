@extends('layouts.workspace')

@section('title', 'Create invoice')
@section('crumb', 'Invoices / Create')

@section('content')
<section class="heading">
    <div>
        <div class="eyebrow">Invoices / Create</div>
        <h1>Create an invoice.</h1>
        <p class="sub">Create a USDC payment request on the configured {{ $network }} network.</p>
    </div>
    <a class="outline" href="{{ route('dashboard.invoices') }}">Back to invoices</a>
</section>

<section class="panel" style="max-width:760px">
    @if($errors->any())
        <div class="callout" role="alert">
            <strong>Please correct the following:</strong>
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($hdEnabled)
        <div class="callout">
            <strong>Automatic address allocation enabled.</strong>
            A unique invoice address will be allocated automatically. Do not enter a payment address.
        </div>
    @elseif($wallets->isNotEmpty())
        <p class="sub">Choose an active wallet that has not already been assigned to an invoice. Previously assigned addresses cannot be reused.</p>
    @else
        <p class="sub">No eligible active wallet is available. Enter a valid EVM address that has never been assigned to an invoice on this network.</p>
    @endif

    <form method="POST" action="{{ route('dashboard.invoices.store') }}">
        @csrf

        <div style="margin-bottom:18px">
            <label for="amount">Amount (USDC)</label>
            <input id="amount" name="amount" type="text" inputmode="decimal"
                   value="{{ old('amount') }}" required
                   pattern="[0-9]{1,24}(\.[0-9]{1,6})?"
                   placeholder="e.g. 25.50" autocomplete="off">
            @error('amount')<p class="muted">{{ $message }}</p>@enderror
        </div>

        @if(!$hdEnabled && $wallets->isNotEmpty())
            <div style="margin-bottom:18px">
                <label for="wallet_id">Payment wallet</label>
                <select id="wallet_id" name="wallet_id" required>
                    <option value="">Choose an unused active wallet</option>
                    @foreach($wallets as $wallet)
                        <option value="{{ $wallet->id }}" @selected((string) old('wallet_id') === (string) $wallet->id)>
                            {{ $wallet->label ?: 'Wallet' }} — {{ $wallet->address }}
                        </option>
                    @endforeach
                </select>
                @error('wallet_id')<p class="muted">{{ $message }}</p>@enderror
            </div>
        @elseif(!$hdEnabled)
            <div style="margin-bottom:18px">
                <label for="payment_address">EVM payment address</label>
                <input id="payment_address" name="payment_address" type="text"
                       value="{{ old('payment_address') }}" required
                       pattern="0x[a-fA-F0-9]{40}" minlength="42" maxlength="42"
                       placeholder="0x..." autocomplete="off" spellcheck="false">
                @error('payment_address')<p class="muted">{{ $message }}</p>@enderror
            </div>
        @endif

        <div style="margin-bottom:18px">
            <label for="expires_in_minutes">Invoice expiry (minutes)</label>
            <input id="expires_in_minutes" name="expires_in_minutes" type="number"
                   min="1" max="10080" step="1" required
                   value="{{ old('expires_in_minutes', 30) }}">
            @error('expires_in_minutes')<p class="muted">{{ $message }}</p>@enderror
        </div>

        <div style="margin-bottom:18px">
            <label for="external_id">Your reference (optional)</label>
            <input id="external_id" name="external_id" type="text" maxlength="255"
                   value="{{ old('external_id') }}" autocomplete="off">
            @error('external_id')<p class="muted">{{ $message }}</p>@enderror
        </div>

        <div style="margin-bottom:22px">
            <label for="description">Description (optional)</label>
            <textarea id="description" name="description" rows="3" maxlength="1000">{{ old('description') }}</textarea>
            @error('description')<p class="muted">{{ $message }}</p>@enderror
        </div>

        <button type="submit">Create invoice</button>
        <a class="outline" href="{{ route('dashboard.invoices') }}">Cancel</a>
    </form>
</section>
@endsection
