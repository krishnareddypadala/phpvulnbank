@extends('layouts.app')

@section('content')
    <h2>Administration</h2>

    <h3>Pending activations</h3>
    <button onclick="loadPending()">Load pending</button>
    <div id="pending"></div>

    <h3>Activate a user</h3>
    <p>Note this action is reachable without the listing above succeeding.</p>
    <form onsubmit="return doActivate(event)">
        <label>Username</label>
        <input type="text" id="user">
        <button type="submit">Activate</button>
    </form>

    <h3>KYC documents</h3>
    <button onclick="loadKyc()">List uploaded documents</button>
    <div id="kyc"></div>
@endsection

@section('scripts')
<script>
async function loadPending() {
    const r = await api('GET', '/api/v2/admin/pending-activations');

    if (r.status !== 200) { document.getElementById('pending').textContent = r.json.error; return; }

    // [VULN-13] Usernames rendered with innerHTML into an option list.
    document.getElementById('pending').innerHTML =
        `<p>Pending: ${r.json.count}</p><select>` +
        r.json.pending.map(u => `<option value="${u.username}">${u.username}</option>`).join('') +
        '</select>';
}

async function doActivate(e) {
    e.preventDefault();
    const r = await api('POST', '/api/v2/admin/activate', { user: document.getElementById('user').value }, true);
    // [VULN-14] The response reflects the username and is served as text/html.
    render(r.text);
    return false;
}

async function loadKyc() {
    const r = await api('GET', '/api/v2/admin/kyc');
    document.getElementById('kyc').innerHTML = r.json.documents
        .map(d => `<p><a href="${d.download}">${d.name}</a></p>`).join('') || '<p>none</p>';
}
</script>
@endsection
