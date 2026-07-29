@extends('layouts.app')

@section('content')
    <h2>Feedback</h2>

    <form onsubmit="return sendFeedback(event)">
        <label>Feedback</label>
        <input type="text" id="fb" size="60">
        <button type="submit">Send Feedback</button>
    </form>

    <h3>All customer feedback (admin only)</h3>
    <button onclick="loadAll()">Load</button>
    <div id="all"></div>
@endsection

@section('scripts')
<script>
async function sendFeedback(e) {
    e.preventDefault();
    const r = await api('PUT', '/api/v2/feedback/me', { fb: document.getElementById('fb').value });
    renderText(r.json ? r.json.message : r.text);
    return false;
}

async function loadAll() {
    const r = await api('GET', '/api/v2/feedback');

    if (r.status !== 200) { renderText(r.json ? r.json.error : r.text); return; }

    // [VULN-13: Stored XSS -- the render half] innerHTML on customer-supplied
    // text, displayed to an administrator. This is where a payload planted by
    // any user fires in an admin's session.
    document.getElementById('all').innerHTML = r.json.feedback
        .map(f => `<p><b>${f.username}</b>: ${f.feedback}</p>`).join('');
}
</script>
@endsection
