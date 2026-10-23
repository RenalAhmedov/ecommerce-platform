<?php

namespace Tests\Feature\Api;

use App\Models\User;
use RedisException;
use Tests\Feature\Api\Concerns\AuthenticatesWithNestToken;
use Tests\Feature\FeatureTestCase;

/** The admin API accepts the access tokens issued by the Nest api-gateway, and only those of admins. */
class JwtAuthenticationTest extends FeatureTestCase
{
    use AuthenticatesWithNestToken;

    public function test_requests_without_a_token_get_401(): void
    {
        $this->getJson('/admin/api/products')->assertUnauthorized();
    }

    public function test_invalid_tokens_get_401(): void
    {
        $tokens = [
            'wrong secret' => $this->tokenFor($this->admin, secret: 'another-secret-another-secret-12345'),
            'expired' => $this->tokenFor($this->admin, ['iat' => time() - 7200, 'exp' => time() - 3600]),
            'no jti' => $this->tokenFor($this->admin, ['jti' => null]),
            'garbage' => 'not.a.jwt',
            'deleted user' => $this->tokenFor(tap(User::factory()->admin()->create())->delete()),
        ];

        foreach ($tokens as $case => $token) {
            $this->api($token)->getJson('/admin/api/products')->assertUnauthorized();
        }
    }

    public function test_a_token_revoked_by_a_nest_logout_gets_401(): void
    {
        $token = $this->tokenFor($this->admin, ['jti' => 'revoked-jti']);
        $this->revokedTokens->shouldReceive('isRevoked')->with('revoked-jti')->andReturnTrue();

        $this->api($token)->getJson('/admin/api/products')->assertUnauthorized();
    }

    public function test_the_revocation_check_fails_open_when_redis_is_down(): void
    {
        $this->revokedTokens->shouldReceive('isRevoked')->andThrow(new RedisException('Connection refused'));

        $this->api()->getJson('/admin/api/products')->assertOk();
    }

    public function test_customer_tokens_get_403(): void
    {
        $customer = User::factory()->create();

        $this->api($this->tokenFor($customer))->getJson('/admin/api/products')->assertForbidden();
    }

    public function test_the_role_comes_from_the_database_not_the_token(): void
    {
        $customer = User::factory()->create();

        $this->api($this->tokenFor($customer, ['role' => 'admin']))->getJson('/admin/api/products')->assertForbidden();
    }
}
