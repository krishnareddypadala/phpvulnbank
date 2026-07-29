@extends('layouts.app')

@section('content')
    <h2>Register new user ({{ strtoupper($mode) }})</h2>

    <form onsubmit="return doRegister(event)">
        <label>Name</label><input type="text" id="name">
        <label>Password</label><input type="password" id="pwd">
        <label>Email</label><input type="text" id="email">
        <label>Phone</label><input type="text" id="tel">
        <button type="submit">Register</button>
    </form>

    <p><a href="/">Click here to login</a></p>
@endsection

@section('scripts')
<script>
const MODE = @json($mode);

async function doRegister(e) {
    e.preventDefault();
    const v = id => document.getElementById(id).value;

    let res;
    if (MODE === 'xml') {
        // The legacy client built XML by string concatenation, so the fields
        // are injectable into the document itself. Preserved.
        //
        // NOTE the split declaration below. Writing the XML declaration as one
        // literal would put a bare left-angle-bracket-question-mark in this
        // file, which Blade compiles into a PHP SHORT OPEN TAG -- a fatal
        // syntax error on any host with short_open_tag=On, as the Debian
        // php:8.3-apache image has. (This comment cannot spell it out either,
        // for exactly the same reason.) A build concern, not a lesson: the XML
        // sent to the server is byte-identical.
        const xml = '<' + '?xml version="1.0" encoding="UTF-8"?' + '>'
            + '<root>'
            + '<name>' + v('name') + '</name>'
            + '<password>' + v('pwd') + '</password>'
            + '<email>' + v('email') + '</email>'
            + '<tel>' + v('tel') + '</tel>'
            + '</root>';
        res = await fetch('/api/v2/register/xml', { method: 'POST', body: xml });
    } else {
        // [VULN-67] The legacy client posted a JSON body while declaring
        // application/x-www-form-urlencoded. That mismatch is preserved --
        // the server reads the raw body regardless of the declared type.
        res = await fetch('/api/v2/register/json', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: JSON.stringify({ name: v('name'), pwd: v('pwd'), email: v('email'), tel: v('tel') }),
        });
    }

    render(await res.text());
    return false;
}
</script>
@endsection
