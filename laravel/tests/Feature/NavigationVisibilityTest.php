<?php

namespace Tests\Feature;

use Database\Seeders\BankTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The navigation hides post-login links from signed-out visitors.
 *
 * This is presentation only, and the second test here is the important one: it
 * pins down that hiding the links did NOT gate the endpoints. If someone later
 * "tidies up" by adding auth middleware to those routes to match the nav, that
 * test fails and the broken-access-control lessons are preserved.
 */
class NavigationVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BankTableSeeder::class);
    }

    public function test_signed_out_visitor_sees_only_public_links(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Login', false);
        $response->assertSee('Register', false);

        foreach (['>Profile<', '>Transfer<', '>Feedback<', '>Admin<', '>Account Lookup<', '>KYC Upload<'] as $hidden) {
            $response->assertDontSee($hidden, false);
        }
    }

    public function test_signed_in_user_sees_the_full_navigation(): void
    {
        $this->postJson('/api/v2/auth/login', ['uname' => 'krishna', 'pwd' => 'happy123$'])->assertOk();

        $response = $this->get('/profile');

        $response->assertOk();
        foreach (['Profile', 'Transfer', 'Feedback', 'Admin', 'Account Lookup', 'Signout'] as $shown) {
            $response->assertSee($shown, false);
        }
        $response->assertSee('signed in as krishna', false);
    }

    /**
     * Hiding the links must NOT have gated the endpoints. These are the
     * broken-access-control lessons (VULN-11, VULN-12) and they stay reachable
     * with no session at all.
     */
    public function test_hiding_links_did_not_gate_the_endpoints(): void
    {
        // No authentication anywhere in this test.
        $this->getJson('/api/v2/accounts/2')->assertOk();
        $this->post('/api/v2/admin/activate', ['user' => 'srikanth'])->assertOk();
        $this->getJson('/api/v2/admin/kyc')->assertOk();
        $this->post('/api/v2/tools/exec', ['cmd' => 'echo still-open'])
            ->assertOk()
            ->assertSee('still-open', false);

        $this->assertDatabaseHas('banktable', ['username' => 'srikanth', 'active' => 1]);
    }
}
