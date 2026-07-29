@extends('layouts.app')

@section('content')
    <h2>API documentation</h2>

    <p>
        Machine-readable spec:
        <a href="/api/v2/openapi.json"><code>/api/v2/openapi.json</code></a>
        &mdash; load it into Postman, Insomnia or Burp.
        Every operation carries an <code>x-vuln</code> field naming the lessons it hosts,
        and <code>x-auth-enforced</code> showing whether authentication is actually required.
    </p>

    <div id="swagger-ui"></div>

    {{-- Shown only if Swagger UI cannot be fetched, e.g. an air-gapped lab. --}}
    <div id="fallback" style="display:none">
        <h3>Endpoint reference</h3>
        <p>
            Swagger UI could not be loaded from the CDN &mdash; this instance has no
            internet access. The spec itself is served locally and is unaffected;
            the table below is generated from it.
        </p>
        <table id="fallback-table" border="1" cellpadding="6" cellspacing="0"
               style="border-collapse:collapse;font-size:13px"></table>
    </div>
@endsection

@section('scripts')
{{--
    Swagger UI is loaded from a CDN rather than bundled. That keeps the repo
    free of a JS build step, which the api-refactor design doc argues for --
    a bundler would also obscure the deliberate innerHTML sinks the XSS lessons
    depend on.

    The trade-off is that an isolated lab with no egress cannot fetch it, so the
    page degrades to a table built from the local spec instead of showing
    nothing. The spec endpoint never depends on the CDN.
--}}
<link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
<script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"
        onerror="swaggerUnavailable()"></script>
<script>
function swaggerUnavailable() {
    document.getElementById('swagger-ui').style.display = 'none';
    document.getElementById('fallback').style.display = 'block';

    fetch('/api/v2/openapi.json')
        .then(r => r.json())
        .then(spec => {
            const rows = [['Method', 'Path', 'Auth enforced', 'Lessons']];
            for (const [path, ops] of Object.entries(spec.paths)) {
                for (const [method, op] of Object.entries(ops)) {
                    rows.push([
                        method.toUpperCase(),
                        path,
                        op['x-auth-enforced'] ? 'yes' : 'NO',
                        (op['x-vuln'] || []).join(', ') || '—',
                    ]);
                }
            }
            // Built with textContent, not innerHTML -- this table is
            // documentation, not one of the XSS lessons.
            const table = document.getElementById('fallback-table');
            rows.forEach((cells, i) => {
                const tr = document.createElement('tr');
                cells.forEach(c => {
                    const cell = document.createElement(i === 0 ? 'th' : 'td');
                    cell.textContent = c;
                    tr.appendChild(cell);
                });
                table.appendChild(tr);
            });
        });
}

window.addEventListener('load', function () {
    if (typeof SwaggerUIBundle === 'undefined') {
        swaggerUnavailable();
        return;
    }

    SwaggerUIBundle({
        url: '/api/v2/openapi.json',
        dom_id: '#swagger-ui',
        deepLinking: true,
        tryItOutEnabled: true,
        displayRequestDuration: true,
    });
});
</script>
@endsection
