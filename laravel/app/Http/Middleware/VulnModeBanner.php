<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Injects the lab warning banner into every HTML response.
 *
 * This is a GUARDRAIL, not a lesson. Required by the migration plan §1: this
 * application is deliberately vulnerable, a Laravel app looks legitimate, and
 * it is one `php artisan serve --host=0.0.0.0` away from being reachable by
 * something that should not reach it. Every rendered page says so.
 *
 * Do not remove this to make screenshots look tidier.
 */
class VulnModeBanner
{
    private const BANNER = <<<'HTML'
    <div style="position:sticky;top:0;z-index:2147483647;background:#b30000;color:#fff;
                font:bold 13px/1.5 system-ui,sans-serif;padding:8px 12px;text-align:center;
                letter-spacing:.02em">
      &#9888; INTENTIONALLY VULNERABLE &mdash; TRAINING USE ONLY.
      Never expose this host to a network you do not control.
    </div>
    HTML;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $type = $response->headers->get('Content-Type', '');

        if (! str_contains($type, 'text/html')) {
            return $response;
        }

        $content = $response->getContent();

        if ($content === false || $content === '') {
            return $response;
        }

        // Prefer just inside <body>; fall back to prepending for the bare
        // HTML fragments some of the ported endpoints return.
        if (preg_match('/<body\b[^>]*>/i', $content, $m)) {
            $content = str_replace($m[0], $m[0].self::BANNER, $content);
        } else {
            $content = self::BANNER.$content;
        }

        $response->setContent($content);

        return $response;
    }
}
