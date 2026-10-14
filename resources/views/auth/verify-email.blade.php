<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Verify your email — Arcflow</title>
    <style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;background:#f5f3ed;color:#202a2c;font-family:Inter,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;display:flex;flex-direction:column}.top{height:72px;display:flex;align-items:center;justify-content:space-between;max-width:980px;width:100%;margin:auto;padding:0 24px}.brand{font:700 23px Georgia,serif;letter-spacing:-.06em;text-decoration:none}.mark{display:inline-grid;place-items:center;width:29px;height:29px;border:1px solid #08765f;color:#08765f;border-radius:8px;font:15px sans-serif;margin-right:8px}main{flex:1;display:grid;place-items:center;padding:20px 16px 60px}.box{width:100%;max-width:460px;background:#fffefa;border:1px solid #e4e4dc;border-radius:13px;padding:32px;box-shadow:0 18px 60px #202a2c0d}.eyebrow{font-size:10px;letter-spacing:.16em;text-transform:uppercase;font-weight:750;color:#08765f}h1{font:400 32px/1.15 Georgia,serif;letter-spacing:-.04em;margin:12px 0}.sub{font-size:13px;line-height:1.8;color:#707a7b}.notice{background:#e5f2ec;border:1px solid #c6e2d6;color:#17634f;border-radius:8px;padding:12px;font-size:12px;line-height:1.6;margin:18px 0}.error{background:#fbece7;border:1px solid #efcdc2;color:#8c4535;border-radius:8px;padding:10px 12px;font-size:12px;margin:16px 0}button{width:100%;padding:14px;border:0;border-radius:8px;background:#08765f;color:white;font-weight:700;cursor:pointer}button:hover{background:#06634f}.help{font-size:12px;line-height:1.7;color:#707a7b;margin:18px 0 0}form{margin-top:18px}.logout{margin-top:12px}.logout button{background:transparent;color:#08765f;border:1px solid #cbd7d0}
    </style>
</head>
<body>
<header class="top">
    <a class="brand" href="/"><span class="mark">A</span>arcflow</a>
</header>
<main>
    <section class="box">
        <div class="eyebrow">One more step</div>
        <h1>Verify your email.</h1>
        <p class="sub">We’ve created your account. Open the verification email we sent you and follow its link before accessing your merchant dashboard. Check your spam folder if it doesn’t arrive shortly.</p>

        @if(session('status') === 'verification-link-sent')
            <div class="notice" role="status">A fresh verification link has been sent to your email address.</div>
        @endif

        @if($errors->any())
            <div class="error" role="alert">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit">Resend verification email</button>
        </form>

        <p class="help">After verifying, use the link in your email to continue to your dashboard.</p>

        <form class="logout" method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit">Sign out</button>
        </form>
    </section>
</main>
</body>
</html>
