<?php

namespace Tests\Feature\Users;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_the_list_filters_by_search_role_and_deleted(): void
    {
        $ada = User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.test']);
        $grace = User::factory()->admin()->create(['name' => 'Grace Hopper']);
        $gone = tap(User::factory()->create(['name' => 'Gone Customer']))->delete();
        Order::factory()->count(2)->for($ada)->create();

        $this->actingAs($this->admin)->get('/users?search=lovelace')
            ->assertOk()->assertSee('ada@example.test')->assertDontSee($grace->email);

        $this->actingAs($this->admin)->get('/users?role=admin')
            ->assertSee($grace->email)->assertDontSee('ada@example.test');

        $this->actingAs($this->admin)->get('/users?trashed=1')
            ->assertSee($gone->email)->assertSee('Restore')->assertDontSee('ada@example.test');
    }

    public function test_an_admin_creates_a_user_the_nest_api_can_log_in(): void
    {
        $this->actingAs($this->admin)->post('/users', [
            'name' => 'New Customer',
            'email' => '  New.Customer@Example.TEST ',
            'password' => 'secret-password',
            'role' => 'customer',
        ])->assertRedirect('/users');

        $user = User::where('email', 'new.customer@example.test')->sole();
        $this->assertSame(UserRole::Customer, $user->role);
        $this->assertStringStartsWith('$2y$', $user->password);
        $this->assertTrue(Hash::check('secret-password', $user->password));
    }

    public function test_emails_are_unique_case_insensitively_including_deleted_users(): void
    {
        User::factory()->create(['email' => 'taken@example.test']);
        User::factory()->create(['email' => 'deleted@example.test'])->delete();

        foreach (['TAKEN@example.test', 'deleted@example.test'] as $email) {
            $this->actingAs($this->admin)
                ->post('/users', ['name' => 'X', 'email' => $email, 'password' => 'secret-password', 'role' => 'customer'])
                ->assertSessionHasErrors(['email' => 'Email is already registered.']);
        }
    }

    public function test_passwords_follow_the_nest_limits(): void
    {
        foreach (['short', str_repeat('a', 73)] as $password) {
            $this->actingAs($this->admin)
                ->post('/users', ['name' => 'X', 'email' => 'x@example.test', 'password' => $password, 'role' => 'customer'])
                ->assertSessionHasErrors('password');
        }
    }

    public function test_an_update_keeps_the_password_when_left_empty(): void
    {
        $user = User::factory()->create(['password' => 'original-password']);
        $token = $user->remember_token;

        $this->actingAs($this->admin)->put("/users/{$user->id}", [
            'name' => 'Renamed', 'email' => $user->email, 'password' => '', 'role' => 'customer',
        ])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('Renamed', $user->name);
        $this->assertTrue(Hash::check('original-password', $user->password));
        $this->assertSame($token, $user->remember_token);
    }

    public function test_a_new_password_also_signs_out_remembered_sessions(): void
    {
        $user = User::factory()->create();
        $token = $user->remember_token;

        $this->actingAs($this->admin)->put("/users/{$user->id}", ['password' => 'brand-new-password'])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertTrue(Hash::check('brand-new-password', $user->password));
        $this->assertNotSame($token, $user->remember_token);
    }

    public function test_an_email_change_cannot_take_another_users_email(): void
    {
        User::factory()->create(['email' => 'first@example.test']);
        $second = User::factory()->create(['email' => 'second@example.test']);

        $this->actingAs($this->admin)->put("/users/{$second->id}", ['email' => 'First@Example.test'])->assertSessionHasErrors('email');
        $this->actingAs($this->admin)->put("/users/{$second->id}", ['email' => 'SECOND@example.test'])->assertSessionHasNoErrors();
    }

    public function test_a_customer_can_be_promoted_and_an_admin_demoted(): void
    {
        $customer = User::factory()->create();
        $otherAdmin = User::factory()->admin()->create();

        $this->actingAs($this->admin)->put("/users/{$customer->id}", ['role' => 'admin'])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->put("/users/{$otherAdmin->id}", ['role' => 'customer'])->assertSessionHasNoErrors();

        $this->assertTrue($customer->refresh()->isAdmin());
        $this->assertFalse($otherAdmin->refresh()->isAdmin());
    }

    public function test_admins_cannot_demote_or_delete_themselves(): void
    {
        User::factory()->admin()->create();

        $this->actingAs($this->admin)->put("/users/{$this->admin->id}", ['role' => 'customer'])
            ->assertSessionHasErrors(['role' => 'You cannot demote your own admin account.']);
        $this->actingAs($this->admin)->delete("/users/{$this->admin->id}")
            ->assertSessionHasErrors(['user' => 'You cannot delete your own admin account.']);

        $this->assertTrue($this->admin->refresh()->isAdmin());
        $this->assertNotSoftDeleted($this->admin);
    }

    /** Admins A and B demote each other at the same moment: the second request must not leave zero admins. */
    public function test_the_last_admin_cannot_be_removed(): void
    {
        $other = User::factory()->admin()->create();
        $users = app(UserService::class);

        $users->update($other, ['role' => 'customer'], $this->admin);

        try {
            $users->update($this->admin, ['role' => 'customer'], $other);
            $this->fail('The last admin was demoted.');
        } catch (ValidationException $e) {
            $this->assertSame(['role' => ['You cannot demote the last admin.']], $e->errors());
        }

        $this->assertSame([$this->admin->id], User::where('role', UserRole::Admin)->pluck('id')->all());
    }

    public function test_deleting_keeps_the_users_orders_and_restore_brings_the_account_back(): void
    {
        $customer = User::factory()->create();
        $order = Order::factory()->for($customer)->create();

        $this->actingAs($this->admin)->delete("/users/{$customer->id}")->assertRedirect('/users');
        $this->assertSoftDeleted($customer);
        $this->assertTrue($order->refresh()->user()->withTrashed()->first()->is($customer));

        $this->actingAs($this->admin)->patch("/users/{$customer->id}/restore")->assertRedirect('/users');
        $this->assertNotSoftDeleted($customer);
    }

    public function test_admin_login_and_password_reset_ignore_email_case(): void
    {
        $this->post('/logout');
        $admin = User::factory()->admin()->create(['email' => 'boss@example.test']);

        $this->post('/login', ['email' => '  Boss@Example.TEST', 'password' => 'password']);

        $this->assertAuthenticatedAs($admin);
    }
}
