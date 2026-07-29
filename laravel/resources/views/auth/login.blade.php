@extends('layouts.app')

@section('content')
    <h2>Enter username and password</h2>

    <form onsubmit="doLogin(event)">
        <label>username</label>
        <input type="text" id="uname" name="uname">
        <label>password</label>
        <input type="password" id="pwd" name="pwd">
        <button type="submit">login</button>
    </form>

    <p>
        <a href="/register/xml">Register (XML)</a> |
        <a href="/register/json">Register (JSON)</a>
    </p>
@endsection

@section('scripts')
<script>
async function doLogin(e) {
    e.preventDefault();
    const r = await api('POST', '/api/v2/auth/login', {
        uname: document.getElementById('uname').value,
        pwd: document.getElementById('pwd').value,
    }, true);

    if (r.status === 200 && r.json) {
        window.location = '/profile';
        return;
    }

    // [VULN-14] The failure body is attacker-controlled and served as
    // text/html by the API. Rendering it with innerHTML executes it.
    render(r.text);
}
</script>
@endsection
