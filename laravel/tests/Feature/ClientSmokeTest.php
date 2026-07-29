<?php

namespace Tests\Feature;

use Database\Seeders\BankTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The browser client is a lesson host, not just a UI -- three vulnerabilities
 * only exist because it does. These assert it is actually there and wired up.
 */
class ClientSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BankTableSeeder::class);
    }

    public static function pages(): array
    {
        return [
            ['/'], ['/profile'], ['/transfer'], ['/feedback'],
            ['/admin'], ['/lookup'], ['/kyc'], ['/register/json'], ['/register/xml'],
        ];
    }

    #[DataProvider('pages')]
    public function test_page_renders(string $path): void
    {
        $this->get($path)->assertOk();
    }

    #[DataProvider('pages')]
    public function test_lab_banner_is_present(string $path): void
    {
        // Required guardrail: every rendered page must say what this app is.
        $this->get($path)->assertSee('INTENTIONALLY VULNERABLE', false);
    }

    /** VULN-13/14 depend on the client writing API data with innerHTML. */
    public function test_client_still_uses_innerhtml(): void
    {
        $this->get('/')->assertSee('innerHTML', false);
    }

    /** VULN-20: the demo credentials are advertised in the footer, with the typo corrected. */
    public function test_credentials_are_disclosed_in_footer(): void
    {
        $response = $this->get('/');
        $response->assertSee('krishna1$', false);
        $response->assertSee('happy123$', false);
        $response->assertDontSee('happay123$', false);
    }

    /** VULN-40: no framing or transport protections are set. */
    public function test_security_headers_are_absent(): void
    {
        $response = $this->get('/');
        $response->assertHeaderMissing('X-Frame-Options');
        $response->assertHeaderMissing('Content-Security-Policy');
        $response->assertHeaderMissing('Strict-Transport-Security');
    }

    /** Legacy URLs stay resolvable so existing write-ups and DAST baselines survive. */
    public function test_legacy_php_urls_redirect(): void
    {
        $this->get('/login.php')->assertRedirect('/');
        $this->get('/transfer.php')->assertRedirect('/transfer');
        $this->get('/displaydata.php')->assertRedirect('/lookup');
    }
}
