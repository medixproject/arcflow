<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f3ed">
    <meta name="robots" content="noindex, nofollow">
    <title>Sign in — Arcflow</title>
    <style>
        :root{--paper:#f5f3ed;--surface:#fffefa;--ink:#202a2c;--muted:#707a7b;--line:#e4e4dc;--green:#08765f;--mint:#e5f2ec;--serif:Georgia,'Times New Roman',serif;--sans:Inter,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif}*{box-sizing:border-box}body{margin:0;min-height:100vh;background:var(--paper);color:var(--ink);font-family:var(--sans);-webkit-font-smoothing:antialiased;display:flex;flex-direction:column}a{color:inherit}
        .top{height:78px;display:flex;align-items:center;justify-content:space-between;max-width:980px;width:100%;margin:0 auto;padding:0 24px}.brand{display:flex;align-items:center;gap:10px;font-family:var(--serif);font-weight:700;font-size:23px;letter-spacing:-.06em;text-decoration:none}.mark{width:29px;height:29px;display:grid;place-items:center;border:1px solid var(--green);color:var(--green);border-radius:8px;font-family:var(--sans);font-size:15px}.back{font-size:11px;color:var(--muted);text-decoration:none}
        main{flex:1;display:grid;place-items:center;padding:20px 18px 70px}.box{width:100%;max-width:420px;background:var(--surface);border:1px solid var(--line);border-radius:13px;box-shadow:0 18px 60px rgba(32,42,44,.05);padding:34px 32px}.eyebrow{font-size:10px;letter-spacing:.16em;text-transform:uppercase;font-weight:750;color:var(--green)}h1{font-family:var(--serif);font-size:34px;font-weight:400;letter-spacing:-.05em;line-height:1.1;margin:14px 0 8px}.sub{font-size:12px;line-height:1.7;color:var(--muted);margin:0 0 24px}
        label{display:block;font-size:11px;font-weight:650;margin:0 0 7px}.field{margin-bottom:18px}input[type=email],input[type=password]{width:100%;padding:13px 14px;font:inherit;font-size:14px;color:var(--ink);background:#fff;border:1px solid #d5d9d2;border-radius:8px;outline:none}input:focus{border-color:var(--green);box-shadow:0 0 0 3px rgba(8,118,95,.14)}
        .error{background:#fbece7;border:1px solid #efcdc2;color:#8c4535;border-radius:8px;padding:11px 13px;font-size:12px;line-height:1.6;margin-bottom:18px}button{width:100%;padding:14px;border:0;border-radius:8px;background:var(--green);color:#fff;font:inherit;font-size:13px;font-weight:700;cursor:pointer}button:hover{background:#06634f}.help{font-size:10px;line-height:1.8;color:var(--muted);margin:20px 0 0}
        @media(max-width:480px){.box{padding:28px 20px}h1{font-size:30px}.top{height:68px;padding:0 16px}}
    </style>
</head>
<body>
<header class="top">
    <a class="brand" href="/" aria-label="Arcflow home"><span class="mark">A</span> arcflow</a>
    <a class="back" href="/">← Back to site</a>
</header>
<main>
    <form class="box" method="POST" action="{{ url('/login') }}">
        @csrf
        <div class="eyebrow">Merchant workspace</div>
        <h1>Sign in to Arcflow.</h1>
        <p class="sub">View your invoices, payments and anything that needs your attention.</p>

        @if($errors->any())
            <div class="error" role="alert">{{ $errors->first() }}</div>
        @endif

        <div class="field">
            <label for="email">Email address</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" autofocus required>
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>
        </div>
        <button type="submit">Sign in</button>
        <p class="help">New to Arcflow? <a href="{{ route('register') }}">Create an account</a>. Email verification is required before dashboard access.</p>
    </form>
</main>
</body>
</html>
