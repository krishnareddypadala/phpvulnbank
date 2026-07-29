<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The spec is hand-written, so the thing most likely to go wrong is drift:
 * an endpoint added or renamed and the document left behind. These tests make
 * that a failure rather than a slow decay into a misleading document.
 */
class OpenApiSpecTest extends TestCase
{
    public function test_spec_is_served_unauthenticated_and_is_valid_json(): void
    {
        $response = $this->getJson('/api/v2/openapi.json');

        $response->assertOk();
        $this->assertSame('3.1.0', $response->json('openapi'));
        $this->assertNotEmpty($response->json('paths'));
    }

    public function test_spec_warns_that_the_api_is_vulnerable(): void
    {
        $description = $this->getJson('/api/v2/openapi.json')->json('info.description');

        $this->assertStringContainsString('deliberately flawed', $description);
        $this->assertStringContainsString('SECURITY.md', $description);
    }

    /** Every real API route must be documented. */
    public function test_every_api_route_appears_in_the_spec(): void
    {
        $documented = array_keys($this->getJson('/api/v2/openapi.json')->json('paths'));

        $missing = [];

        foreach (Route::getRoutes() as $route) {
            $uri = '/'.ltrim($route->uri(), '/');

            if (! str_starts_with($uri, '/api/')) {
                continue;
            }

            if (! in_array($uri, $documented, true)) {
                $missing[] = implode('|', $route->methods()).' '.$uri;
            }
        }

        $this->assertSame([], $missing,
            "These API routes are not in the OpenAPI spec:\n  ".implode("\n  ", $missing));
    }

    /** And nothing documented may be fictional. */
    public function test_spec_documents_no_nonexistent_routes(): void
    {
        $real = [];

        foreach (Route::getRoutes() as $route) {
            $real[] = '/'.ltrim($route->uri(), '/');
        }

        $fictional = array_values(array_filter(
            array_keys($this->getJson('/api/v2/openapi.json')->json('paths')),
            fn ($path) => ! in_array($path, $real, true)
        ));

        $this->assertSame([], $fictional,
            "The spec documents routes that do not exist:\n  ".implode("\n  ", $fictional));
    }

    /** Each operation must declare its lessons and whether auth is enforced. */
    public function test_every_operation_is_annotated(): void
    {
        $paths = $this->getJson('/api/v2/openapi.json')->json('paths');

        $unannotated = [];

        foreach ($paths as $path => $operations) {
            foreach ($operations as $method => $operation) {
                if (! array_key_exists('x-vuln', $operation) || ! array_key_exists('x-auth-enforced', $operation)) {
                    $unannotated[] = strtoupper($method).' '.$path;
                }
            }
        }

        $this->assertSame([], $unannotated,
            "Operations missing x-vuln / x-auth-enforced:\n  ".implode("\n  ", $unannotated));
    }

    /** The unauthenticated endpoints must be labelled as such -- that is the teaching value. */
    public function test_ungated_endpoints_are_flagged(): void
    {
        $paths = $this->getJson('/api/v2/openapi.json')->json('paths');

        $this->assertFalse($paths['/api/v2/admin/activate']['post']['x-auth-enforced'],
            'The activate endpoint is ungated (VULN-12) and the spec must say so.');
        $this->assertFalse($paths['/api/v2/tools/exec']['get']['x-auth-enforced']);
        $this->assertTrue($paths['/api/v2/accounts/me']['get']['x-auth-enforced']);
    }
}
