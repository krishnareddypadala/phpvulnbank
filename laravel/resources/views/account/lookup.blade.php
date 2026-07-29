@extends('layouts.app')

@section('content')
    <h2>Account lookup</h2>
    <p>Ported from the legacy "Show Password" page.</p>

    <form onsubmit="return doLookup(event, false)">
        <label>Account number</label>
        <input type="text" id="aid">
        <button type="submit">Get account</button>
    </form>

    <h3>Remediated variant</h3>
    <p>
        Uses a bound parameter, so the SQL injection is genuinely fixed.
        The missing ownership check and the password disclosure are not.
    </p>
    <button onclick="doLookup(event, true)">Get account (safe query)</button>
@endsection

@section('scripts')
<script>
async function doLookup(e, safe) {
    e.preventDefault();
    const aid = document.getElementById('aid').value;
    const url = '/api/v2/accounts/' + aid + (safe ? '/safe' : '');
    const r = await api('GET', url);

    if (r.status !== 200) { renderText(r.json ? r.json.error : r.text); return false; }

    // Rendered with innerHTML: the username column is attacker-controlled at
    // registration, so this is a stored XSS sink as well as a disclosure one.
    render(`<p>${r.json.username} &mdash; ${r.json.password_hash} &mdash; ${r.json.balance}</p>`);
    return false;
}
</script>
@endsection
