@extends('layouts.app')

@section('content')
    <h2>Your account</h2>
    <div id="profile">loading…</div>
    <p><a href="/logout" onclick="doLogout(event)">Signout</a></p>
@endsection

@section('scripts')
<script>
async function load() {
    const r = await api('GET', '/api/v2/accounts/me');

    if (r.status !== 200) { render('Not logged in. <a href="/">Login</a>'); return; }

    const a = r.json;

    // [VULN-13: Stored XSS] The feedback field is rendered with innerHTML.
    // A payload another user stored through PUT /api/v2/feedback/me executes
    // here, in the viewer's session. Rendering with textContent would close it.
    document.getElementById('profile').innerHTML = `
        <p>Hello ${a.username}</p>
        <p>Account number: ${a.acno}</p>
        <p>Email: ${a.email}</p>
        <p>Phone: ${a.mobile}</p>
        <p>Balance: ${a.balance}</p>
        <p>Your feedback: ${a.feedback}</p>
    `;
}

async function doLogout(e) {
    e.preventDefault();
    await api('POST', '/api/v2/auth/logout');
    window.location = '/';
}

load();
</script>
@endsection
