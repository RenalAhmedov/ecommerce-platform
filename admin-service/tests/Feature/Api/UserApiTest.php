<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Tests\Feature\Api\Concerns\AuthenticatesWithNestToken;
use Tests\Feature\FeatureTestCase;

class UserApiTest extends FeatureTestCase
{
    use AuthenticatesWithNestToken;

    public function test_users_through_the_api(): void
    {
        $this->api()->postJson('/admin/api/users', ['name' => 'Api User', 'email' => 'API@example.test', 'password' => 'secret-password', 'role' => 'customer'])
            ->assertCreated()
            ->assertJsonPath('data.email', 'api@example.test')
            ->assertJsonMissingPath('data.password');

        $user = User::where('email', 'api@example.test')->sole();

        $this->api()->getJson('/admin/api/users?role=customer')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.ordersCount', 0);
        $this->api()->patchJson("/admin/api/users/{$user->id}", ['role' => 'admin'])->assertOk()->assertJsonPath('data.role', 'admin');
        $this->api()->deleteJson("/admin/api/users/{$this->admin->id}")->assertUnprocessable()->assertJsonValidationErrors('user');
        $this->api()->deleteJson("/admin/api/users/{$user->id}")->assertNoContent();
        $this->api()->patchJson("/admin/api/users/{$user->id}/restore")->assertOk()->assertJsonPath('data.deletedAt', null);
    }
}
