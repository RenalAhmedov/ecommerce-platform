<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Repositories\RevokedTokenRepository;
use Illuminate\Support\Facades\Redis;
use Tests\Feature\Api\Concerns\AuthenticatesWithNestToken;
use Tests\Feature\FeatureTestCase;

/** Shared session: an api-gateway token opens the panel, and the two then end together. Real Redis throughout. */
class TokenLoginTest extends FeatureTestCase
{
    use AuthenticatesWithNestToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(RevokedTokenRepository::class, new RevokedTokenRepository);
    }

    public function test_an_admin_token_opens_the_panel_without_a_password(): void
    {
        $this->post('/login/token', ['token' => $this->tokenFor($this->admin)])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($this->admin);
        $this->get('/dashboard')->assertOk();
    }

    public function test_bad_revoked_and_customer_tokens_get_the_same_refusal(): void
    {
        $revoked = $this->tokenFor($this->admin, ['jti' => 'revoked-before-login']);
        app(RevokedTokenRepository::class)->revoke('revoked-before-login', time() + 60);

        foreach (['not.a.jwt', $revoked, $this->tokenFor(User::factory()->create())] as $token) {
            $this->post('/login/token', ['token' => $token])
                ->assertSessionHasErrors(['token' => 'This access token cannot open the admin panel.']);
        }

        $this->assertGuest();
    }

    public function test_a_logout_in_the_api_gateway_ends_the_panel_session(): void
    {
        $this->post('/login/token', ['token' => $this->tokenFor($this->admin, ['jti' => 'logged-out-in-nest'])]);

        // What the api-gateway's POST /api/auth/logout writes.
        Redis::connection('shared')->set('auth:revoked-token:logged-out-in-nest', '1', 'EX', 60);

        $this->get('/dashboard')
            ->assertRedirect(route('login', absolute: false))
            ->assertSessionHas('status', 'Your session ended because its access token was logged out or has expired.');
        $this->assertGuest();
    }

    public function test_the_panel_session_ends_when_its_token_expires(): void
    {
        $this->actingAs($this->admin)
            ->withSession(['auth.access_token' => ['jti' => 'expired', 'sub' => $this->admin->id, 'iat' => time() - 3_600, 'exp' => time() - 1]])
            ->get('/dashboard')
            ->assertRedirect(route('login', absolute: false));

        $this->assertGuest();
    }

    public function test_a_panel_logout_also_logs_the_token_out_of_the_api_gateway(): void
    {
        $token = $this->tokenFor($this->admin, ['jti' => 'logged-out-in-panel']);
        $this->post('/login/token', ['token' => $token]);

        $this->post('/logout')->assertRedirect(route('login', absolute: false));

        $this->assertSame('1', Redis::connection('shared')->get('auth:revoked-token:logged-out-in-panel'));
        $this->api($token)->getJson('/admin/api/products')->assertUnauthorized();
    }
}
