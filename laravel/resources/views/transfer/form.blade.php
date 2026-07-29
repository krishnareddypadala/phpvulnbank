@extends('layouts.app')

@section('content')
    <h2>Fund transfer</h2>

    {{--
        [VULN-18: Client-side-only validation] Intentional.

        The legacy page validated the destination account with a JavaScript
        isNaN() check. It is reproduced below and it is, self-evidently,
        irrelevant -- POST /api/v2/transfers is callable directly. Keeping it
        makes the point better than removing it would: the control exists, it
        is visible, and it protects nothing.
    --}}
    <form name="transfer" onsubmit="return doTransfer(event)">
        <label>To Account</label>
        <input type="text" id="tacno" name="tacno">
        <label>Transfer Amount</label>
        <input type="text" id="tamount" name="tamount">
        <button type="submit">Transfer</button>
    </form>

    <h3>Protected variant</h3>
    <p>The same operation with a CSRF token. Every other flaw is unchanged.</p>
    <form onsubmit="return doProtectedTransfer(event)">
        <label>To Account</label>
        <input type="text" id="ptacno">
        <label>Transfer Amount</label>
        <input type="text" id="ptamount">
        <button type="submit">Transfer (protected)</button>
    </form>
@endsection

@section('scripts')
<script>
function checkInp(v) {
    if (isNaN(v)) { render(v + ' is not a number'); return false; }
    return true;
}

async function doTransfer(e) {
    e.preventDefault();
    const tacno = document.getElementById('tacno').value;
    if (!checkInp(tacno)) return false;

    // Sent form-encoded on purpose -- see VULN-10 in bootstrap/app.php.
    const r = await api('POST', '/api/v2/transfers', {
        tacno, tamount: document.getElementById('tamount').value,
    }, true);

    render(r.json ? r.json.message : r.text);
    return false;
}

async function doProtectedTransfer(e) {
    e.preventDefault();
    const r = await api('POST', '/api/v2/transfers/protected', {
        tacno: document.getElementById('ptacno').value,
        tamount: document.getElementById('ptamount').value,
        csrftoken: '{{ csrf_token() }}',
    }, true);

    render(r.json ? (r.json.message || r.json.error) : r.text);
    return false;
}
</script>
@endsection
