<?php

namespace Tests\Unit\Services;

use App\Enums\UserRole;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\UserService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;
use Tests\Unit\UnitTestCase;

class UserServiceTest extends UnitTestCase
{
    private UserRepository&MockInterface $users;

    private UserService $service;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->users = $this->mock(UserRepository::class);
        $this->service = app(UserService::class);
        $this->actor = $this->user(1, UserRole::Admin);
    }

    private function user(int $id, UserRole $role, string $email = 'user@example.test'): User
    {
        $user = User::factory()->make(['id' => $id, 'email' => $email, 'role' => $role, 'remember_token' => 'old-token']);
        $user->syncOriginal();

        return $user;
    }

    /** The service re-reads the row under a lock; here the "locked" copy is the same object. */
    private function givenLocked(User $user): void
    {
        $this->users->shouldReceive('lockById')->with($user->id)->andReturn($user);
    }

    private function expectError(string $field, string $message, callable $action): void
    {
        try {
            $action();
            $this->fail("Expected the {$field} error: {$message}");
        } catch (ValidationException $e) {
            $this->assertSame([$field => [$message]], $e->errors());
        }
    }

    public function test_create_locks_the_email_then_checks_it_like_nest(): void
    {
        $created = $this->user(5, UserRole::Customer, 'new@example.test');
        $this->users->shouldReceive('lockEmail')->once()->with('new@example.test')->ordered();
        $this->users->shouldReceive('existsByEmail')->once()->with('new@example.test', null)->andReturnFalse()->ordered();
        $this->users->shouldReceive('create')->once()
            ->with(['name' => 'New', 'email' => 'new@example.test', 'password' => 'secret-password'], UserRole::Customer)
            ->andReturn($created)->ordered();

        $this->assertSame($created, $this->service->create(['name' => 'New', 'email' => 'new@example.test', 'password' => 'secret-password', 'role' => 'customer']));
    }

    public function test_create_refuses_a_registered_email(): void
    {
        $this->users->shouldReceive('lockEmail');
        $this->users->shouldReceive('existsByEmail')->andReturnTrue();
        $this->users->shouldNotReceive('create');

        $this->expectError('email', 'Email is already registered.', fn () => $this->service->create(['name' => 'X', 'email' => 'taken@example.test', 'password' => 'secret-password', 'role' => 'customer']));
    }

    public function test_an_email_change_is_locked_and_checked_against_other_users(): void
    {
        $user = $this->user(5, UserRole::Customer, 'old@example.test');
        $this->givenLocked($user);
        $this->users->shouldReceive('lockEmail')->once()->with('new@example.test');
        $this->users->shouldReceive('existsByEmail')->once()->with('new@example.test', 5)->andReturnFalse();
        $this->users->shouldReceive('save')->once()->with($user);

        $this->assertSame('new@example.test', $this->service->update($user, ['email' => 'new@example.test'], $this->actor)->email);
    }

    public function test_an_unchanged_email_is_not_checked_again(): void
    {
        $user = $this->user(5, UserRole::Customer, 'same@example.test');
        $this->givenLocked($user);
        $this->users->shouldNotReceive('lockEmail');
        $this->users->shouldNotReceive('existsByEmail');
        $this->users->shouldReceive('save')->once();

        $this->service->update($user, ['name' => 'Renamed', 'email' => 'same@example.test'], $this->actor);
    }

    public function test_a_new_password_is_hashed_and_renews_the_remember_token(): void
    {
        $user = $this->user(5, UserRole::Customer);
        $this->givenLocked($user);
        $this->users->shouldReceive('save')->once();

        $this->service->update($user, ['password' => 'brand-new-password'], $this->actor);

        $this->assertTrue(Hash::check('brand-new-password', $user->password));
        $this->assertNotSame('old-token', $user->remember_token);
    }

    public function test_an_empty_password_keeps_the_current_one(): void
    {
        $user = $this->user(5, UserRole::Customer);
        $password = $user->password;
        $this->givenLocked($user);
        $this->users->shouldReceive('save')->once();

        $this->service->update($user, ['password' => null], $this->actor);

        $this->assertSame([$password, 'old-token'], [$user->password, $user->remember_token]);
    }

    public function test_an_admin_cannot_demote_themselves(): void
    {
        $this->givenLocked($this->actor);
        $this->users->shouldNotReceive('lockAdminRoster');
        $this->users->shouldNotReceive('save');

        $this->expectError('role', 'You cannot demote your own admin account.', fn () => $this->service->update($this->actor, ['role' => 'customer'], $this->actor));
    }

    public function test_demoting_another_admin_takes_the_roster_lock(): void
    {
        $other = $this->user(2, UserRole::Admin);
        $this->givenLocked($other);
        $this->users->shouldReceive('lockAdminRoster')->once()->ordered();
        $this->users->shouldReceive('countAdmins')->once()->andReturn(2)->ordered();
        $this->users->shouldReceive('save')->once()->ordered();

        $this->assertSame(UserRole::Customer, $this->service->update($other, ['role' => 'customer'], $this->actor)->role);
    }

    public function test_the_last_admin_cannot_be_demoted(): void
    {
        $other = $this->user(2, UserRole::Admin);
        $this->givenLocked($other);
        $this->users->shouldReceive('lockAdminRoster');
        $this->users->shouldReceive('countAdmins')->andReturn(1);
        $this->users->shouldNotReceive('save');

        $this->expectError('role', 'You cannot demote the last admin.', fn () => $this->service->update($other, ['role' => 'customer'], $this->actor));
    }

    public function test_promoting_a_customer_needs_no_roster_check(): void
    {
        $customer = $this->user(3, UserRole::Customer);
        $this->givenLocked($customer);
        $this->users->shouldNotReceive('lockAdminRoster');
        $this->users->shouldReceive('save')->once();

        $this->assertSame(UserRole::Admin, $this->service->update($customer, ['role' => 'admin'], $this->actor)->role);
    }

    public function test_an_admin_cannot_delete_themselves(): void
    {
        $this->givenLocked($this->actor);
        $this->users->shouldNotReceive('delete');

        $this->expectError('user', 'You cannot delete your own admin account.', fn () => $this->service->delete($this->actor, $this->actor));
    }

    public function test_deleting_a_customer_needs_no_roster_check(): void
    {
        $customer = $this->user(3, UserRole::Customer);
        $this->givenLocked($customer);
        $this->users->shouldNotReceive('lockAdminRoster');
        $this->users->shouldReceive('delete')->once()->with($customer);

        $this->service->delete($customer, $this->actor);
    }

    public function test_a_password_reset_renews_the_remember_token(): void
    {
        $user = $this->user(3, UserRole::Admin);
        $this->users->shouldReceive('updatePasswordAndRememberToken')->once()
            ->withArgs(fn (User $target, string $password, string $token) => $target === $user && $password === 'new-password' && strlen($token) === 60);

        $this->service->resetPassword($user, 'new-password');
    }
}
