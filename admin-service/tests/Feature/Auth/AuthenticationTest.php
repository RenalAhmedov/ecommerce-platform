<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Tests\Feature\FeatureTestCase;

class AuthenticationTest extends FeatureTestCase
{
    public function test_login_screen_can_be_rendered(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_admins_can_log_in(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($admin);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_customers_cannot_log_in_and_get_the_generic_error(): void
    {
        $customer = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $customer->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors(['email' => trans('auth.failed')]);
    }

    public function test_admins_cannot_log_in_with_a_wrong_password(): void
    {
        $admin = User::factory()->admin()->create();

        $this->post('/login', [
            'email' => $admin->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_soft_deleted_admins_cannot_log_in(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->delete();

        $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
    }

    public function test_login_is_locked_after_five_failed_attempts(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (range(1, 5) as $attempt) {
            $this->post('/login', ['email' => $admin->email, 'password' => 'wrong-password']);
        }

        $response = $this->post('/login', ['email' => $admin->email, 'password' => 'password']);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many login attempts', session('errors')->first('email'));
    }

    public function test_admins_can_log_out(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect(route('login', absolute: false));
    }

    public function test_one_ip_cannot_try_many_accounts(): void
    {
        foreach (range(1, 20) as $attempt) {
            $this->post('/login', ['email' => "user{$attempt}@example.test", 'password' => 'wrong-password'])->assertRedirect();
        }

        $this->post('/login', ['email' => 'user21@example.test', 'password' => 'wrong-password'])->assertTooManyRequests();
    }
}
