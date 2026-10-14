<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Create account — Arcflow</title>
    <style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;background:#f5f3ed;color:#202a2c;font-family:Inter,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;display:flex;flex-direction:column}.top{height:72px;display:flex;align-items:center;justify-content:space-between;max-width:980px;width:100%;margin:auto;padding:0 24px}.brand{font:700 23px Georgia,serif;letter-spacing:-.06em;text-decoration:none}.mark{display:inline-grid;place-items:center;width:29px;height:29px;border:1px solid #08765f;color:#08765f;border-radius:8px;font:15px sans-serif;margin-right:8px}main{flex:1;display:grid;place-items:center;padding:20px 16px 60px}.box{width:100%;max-width:460px;background:#fffefa;border:1px solid #e4e4dc;border-radius:13px;padding:30px;box-shadow:0 18px 60px #202a2c0d}.eyebrow{font-size:10px;letter-spacing:.16em;text-transform:uppercase;font-weight:750;color:#08765f}h1{font:400 32px/1.15 Georgia,serif;letter-spacing:-.04em;margin:12px 0 8px}.sub,.help{font-size:12px;line-height:1.7;color:#707a7b}.sub{margin:0 0 22px}.field{margin-bottom:15px}label{display:block;font-size:11px;font-weight:650;margin-bottom:7px}input{width:100%;padding:12px 13px;font:14px inherit;color:#202a2c;background:white;border:1px solid #d5d9d2;border-radius:8px}input:focus{outline:2px solid #08765f55;border-color:#08765f}.error{background:#fbece7;border:1px solid #efcdc2;color:#8c4535;border-radius:8px;padding:10px 12px;font-size:12px;margin-bottom:16px}button{width:100%;padding:14px;border:0;border-radius:8px;background:#08765f;color:white;font-weight:700;cursor:pointer}button:hover{background:#06634f}.help{margin:18px 0 0}.help a{color:#08765f}@media(max-width:480px){.box{padding:24px 20px}.top{padding:0 16px}}
    </style>
</head>
<body>
<header class="top">
    <a class="brand" href="/"><span class="mark">A</span>arcflow</a>
    <a href="/login">Sign in</a>
</header>
<main>
    <form class="box" method="POST" action="{{ route('register.store') }}">
        @csrf
        <div class="eyebrow">Merchant workspace</div>
        <h1>Create your Arcflow account.</h1>
        <p class="sub">Register your workspace to manage invoices and incoming payments.</p>

        @if($errors->any())
            <div class="error" role="alert">{{ $errors->first() }}</div>
        @endif

        <div class="field">
            <label for="name">Your name</label>
            <input id="name" name="name" value="{{ old('name') }}" autocomplete="name" maxlength="255" required>
        </div>
        <div class="field">
            <label for="merchant_name">Business or workspace name</label>
            <input id="merchant_name" name="merchant_name" value="{{ old('merchant_name') }}" autocomplete="organization" maxlength="255" required>
        </div>
        <div class="field">
            <label for="email">Email address</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" maxlength="255" required>
        </div>
        <div class="field">
            <label for="password">Password (at least 12 characters)</label>
            <input id="password" name="password" type="password" autocomplete="new-password" minlength="12" maxlength="128" required>
        </div>
        <div class="field">
            <label for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" maxlength="128" required>
        </div>
        <button type="submit">Create account</button>
        <p class="help">We’ll email you a verification link before you can access your dashboard. Already registered? <a href="/login">Sign in</a>.</p>
    </form>
</main>
</body>
</html>
