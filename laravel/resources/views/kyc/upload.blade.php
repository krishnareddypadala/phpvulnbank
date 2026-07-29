@extends('layouts.app')

@section('content')
    <h2>Know Your Customer (KYC)</h2>
    <p>Please upload your KYC forms in PNG/JPEG format.</p>

    {{--
        The instruction above is the only "validation" in this flow. The
        endpoint accepts anything -- see VULN-04 in KycController.
    --}}
    <form onsubmit="return doUpload(event)" enctype="multipart/form-data">
        <input type="file" id="image" name="image">
        <button type="submit">Upload</button>
    </form>
@endsection

@section('scripts')
<script>
async function doUpload(e) {
    e.preventDefault();
    const f = document.getElementById('image').files[0];
    if (!f) { renderText('choose a file'); return false; }

    const fd = new FormData();
    fd.append('image', f);

    const res = await fetch('/api/v2/kyc', { method: 'POST', body: fd });
    const j = await res.json();

    render(j.url ? `Success &mdash; stored at <a href="${j.url}">${j.url}</a>` : (j.error || 'failed'));
    return false;
}
</script>
@endsection
