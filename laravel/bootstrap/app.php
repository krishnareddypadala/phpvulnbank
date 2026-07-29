<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // ------------------------------------------------------------------
        // [VULN-10: CSRF] Intentional. Do not "fix" -- see docs/vulnerabilities.md
        //
        // The api group is given cookie and session handling so the browser
        // client can authenticate with a session cookie, exactly as the legacy
        // app did. What it is NOT given is VerifyCsrfToken.
        //
        // Both halves are required for the CSRF lesson to be reachable:
        //
        //   1. session-cookie auth, so a cross-site request carries ambient
        //      authority; and
        //   2. endpoints that accept application/x-www-form-urlencoded, so an
        //      HTML form can forge the request at all (see TransferController).
        //
        // A JSON-only API is NOT CSRF-able -- a form cannot send a JSON
        // content type, and a cross-origin fetch that does triggers a preflight
        // a silent attack cannot satisfy. Dropping either half leaves an
        // endpoint that is theoretically unprotected and practically
        // unattackable, which teaches nothing and looks clean to a scanner.
        // See docs/api-refactor.md §5.1.
        //
        // The fix is to add \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken
        // to this group, as the `web` group has by default.
        // ------------------------------------------------------------------
        $middleware->api(prepend: [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
        ]);

        // ------------------------------------------------------------------
        // [VULN: PAYLOAD FIDELITY] Intentional. Do not re-enable.
        //
        // Laravel applies TrimStrings and ConvertEmptyStringsToNull to every
        // request by default. Neither is a security control -- they are input
        // normalisation -- but both silently MUTATE injection payloads:
        //
        //   * MySQL only treats `--` as a comment when it is followed by
        //     whitespace. TrimStrings strips the trailing space from the
        //     classic `' or '1'='1' -- ` payload, turning a working bypass
        //     into a syntax error.
        //   * ConvertEmptyStringsToNull rewrites `param=` to NULL, which
        //     changes what reaches the interpolated SQL.
        //
        // Leaving these on would not "fix" any vulnerability -- it would make
        // published payloads for this lab fail for a reason that has nothing
        // to do with the lesson, which is worse than either outcome. Removing
        // them keeps what the learner types identical to what hits the query.
        // ------------------------------------------------------------------
        $middleware->remove([
            \Illuminate\Foundation\Http\Middleware\TrimStrings::class,
            \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        ]);

        // The lab warning banner is injected into every HTML response.
        $middleware->append(\App\Http\Middleware\VulnModeBanner::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // [VULN-23 / VULN-70: Information disclosure] Intentional.
        // API exceptions render as JSON, and with APP_DEBUG=true that JSON
        // carries the exception message, file path, line number and stack
        // trace. Richer and easier to harvest at scale than the legacy HTML
        // warnings. The fix is APP_DEBUG=false. See SECURITY.md.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
