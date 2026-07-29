<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PHP VULN BANK</title>
    {{--
        [VULN-40: Clickjacking / missing security headers] Intentional.

        There is deliberately no Content-Security-Policy, no X-Frame-Options
        and no Referrer-Policy anywhere in this application. The transfer form
        can therefore be framed and UI-redressed.

        The fix is a middleware setting frame-ancestors 'none' (or
        X-Frame-Options: DENY) on every response.
    --}}
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; }
        header { background:#333; color:#fff; padding:20px; text-align:center; }
        nav { background:#eee; padding:10px; text-align:center; }
        nav a { text-decoration:none; color:#333; margin:0 10px; }
        main { padding:20px; max-width:900px; margin:0 auto; }
        footer { background:#333; color:#fff; padding:20px; text-align:center; font-size:13px; }
        label { display:block; margin:12px 0 4px; }
        input[type=text], input[type=password], input[type=number] { padding:6px; min-width:260px; }
        button { padding:8px 14px; margin-top:14px; cursor:pointer; }
        pre { background:#f5f5f5; padding:12px; overflow-x:auto; }
        .out { margin-top:18px; }
    </style>
</head>
<body>
<header><h1>Welcome to PHP VULN BANK</h1></header>

{{--
    The navigation is rendered from session state: signed-out visitors see only
    Login and Register.

    IMPORTANT -- this is a UI change ONLY, and it does not close any lesson.
    The endpoints behind these links are exactly as reachable as before; several
    of them (VULN-11 account lookup, VULN-12 activate, KYC list and download,
    the tools endpoints) still require no authentication at all.

    If anything this makes VULN-12 sharper rather than weaker. Previously the
    links were visible to everyone, so "the UI is not a control" was easy to
    assume. Now the interface genuinely looks gated -- and the endpoints still
    are not. A learner has to notice that hiding a link changed nothing, which
    is the entire point of broken function-level access control.

    Do not be tempted to add middleware to the routes to "match" this nav.
--}}
<nav>
    @if (session()->has('uname'))
        <a href="/profile">Profile</a> |
        <a href="/transfer">Transfer</a> |
        <a href="/feedback">Feedback</a> |
        <a href="/kyc">KYC Upload</a> |
        <a href="/lookup">Account Lookup</a> |
        <a href="/admin">Admin</a> |
        <a href="/docs">API Docs</a> |
        <a href="#" onclick="navSignout(event)">Signout</a>
        <span style="margin-left:12px;color:#666">signed in as {{ session('uname') }}</span>
    @else
        <a href="/">Login</a> |
        <a href="/register/json">Register (JSON)</a> |
        <a href="/register/xml">Register (XML)</a> |
        <a href="/docs">API Docs</a>
    @endif
</nav>

<main>
    @yield('content')
    <div class="out" id="out"></div>
</main>

<footer>
    {{--
        [VULN-20: Credential disclosure in the UI] Intentional.
        The legacy footer advertised the demo credentials on every page. Kept.

        The legacy text said krishna's password was "happay123$"; the seed data
        says "happy123$". The typo is corrected here -- see
        docs/legacy-mapping.md §2.
    --}}
    <b>Administrator:</b> admin / krishna1$ &nbsp;|&nbsp;
    <b>User:</b> krishna / happy123$
    <br><br>
    Source: <a href="https://github.com/krishnareddypadala/phpvulnbank" style="color:#fff">github</a>
</footer>

<script>
// =============================================================================
// [VULN-13 / VULN-14: DOM-based XSS] INTENTIONAL. DO NOT "FIX".
// =============================================================================
//
// This is the most fragile lesson in the application, because the fix looks so
// obviously correct that linters, contributors and AI assistants all reach for
// it automatically.
//
// Moving the app behind a JSON API removed the server-side XSS sink -- an
// application/json response does not execute script. The vulnerability class
// therefore MOVED rather than disappeared: it is now DOM-based XSS, and it
// lives here, in the one function that writes API data into the page.
//
// Using innerHTML is the vulnerability. Using textContent would close it, and
// would also silently delete the stored-XSS lesson (VULN-13), the reflected
// XSS lesson (VULN-14) and the session-theft chain that depends on them.
//
// See docs/api-refactor.md §5.2. If you are here because a scanner flagged
// this line: it is meant to be flagged.
//
function render(html) {
    document.getElementById('out').innerHTML = html;   // <-- the sink
}

function renderText(text) {
    // The safe counterpart, used for endpoints that are not XSS lessons.
    document.getElementById('out').textContent = text;
}

async function navSignout(e) {
    e.preventDefault();
    await fetch('/api/v2/auth/logout', { method: 'POST' });
    window.location = '/';
}

async function api(method, url, body, form) {
    const opts = { method, headers: {} };
    if (body && form) {
        opts.headers['Content-Type'] = 'application/x-www-form-urlencoded';
        opts.body = new URLSearchParams(body).toString();
    } else if (body) {
        opts.headers['Content-Type'] = 'application/json';
        opts.body = JSON.stringify(body);
    }
    const res = await fetch(url, opts);
    const text = await res.text();
    try { return { status: res.status, json: JSON.parse(text), text }; }
    catch { return { status: res.status, json: null, text }; }
}
</script>

@yield('scripts')
</body>
</html>
