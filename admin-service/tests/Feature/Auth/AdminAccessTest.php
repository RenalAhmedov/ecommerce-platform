<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Tests\Feature\FeatureTestCase;

class AdminAccessTest extends FeatureTestCase
{
    public function test_the_root_url_redirects_to_the_dashboard(): void
    {
        $this->get('/')->assertRedirect('/dashboard');
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login', absolute: false));
    }

    public function test_admins_can_open_the_panel(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/dashboard')->assertOk();
        $this->actingAs($admin)->get('/profile')->assertOk();
    }

    public function test_a_customer_session_is_signed_out(): void
    {
        $customer = User::factory()->create();

        $response = $this->actingAs($customer)->get('/dashboard');

        $response->assertRedirect(route('login', absolute: false));
        $response->assertSessionHasErrors(['email' => 'This panel is for administrators only.']);
        $this->assertGuest();
    }

    public function test_an_admin_demoted_after_login_is_signed_out(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/dashboard')->assertOk();

        $admin->forceFill(['role' => UserRole::Customer])->save();

        $this->get('/dashboard')->assertRedirect(route('login', absolute: false));
        $this->assertGuest();
    }
}
