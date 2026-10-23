<?php

namespace Tests\Feature\Models;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\FeatureTestCase;

class UserTest extends FeatureTestCase
{
    public function test_new_users_are_customers_by_default(): void
    {
        $user = User::create([
            'name' => 'Ada',
            'email' => 'ada@example.test',
            'password' => 'password123',
        ]);

        $this->assertSame(UserRole::Customer, $user->role);
        $this->assertFalse($user->isAdmin());
    }

    public function test_role_cannot_be_mass_assigned(): void
    {
        $this->expectException(MassAssignmentException::class);

        User::make(['role' => UserRole::Admin]);
    }

    public function test_password_is_hashed_with_bcrypt_and_hidden_from_output(): void
    {
        $user = User::factory()->create(['password' => 'password123']);

        $this->assertStringStartsWith('$2y$', $user->password);
        $this->assertTrue(Hash::check('password123', $user->password));
        $this->assertArrayNotHasKey('password', $user->toArray());
        $this->assertArrayNotHasKey('remember_token', $user->toArray());
    }

    public function test_it_accepts_a_password_hash_written_by_the_nest_api(): void
    {
        $cost = config('hashing.bcrypt.rounds');
        $nestHash = password_hash('password123', PASSWORD_BCRYPT, ['cost' => $cost]);
        $user = User::factory()->create(['password' => $nestHash]);

        $this->assertSame($nestHash, $user->password);
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_soft_deleted_users_are_hidden_but_kept(): void
    {
        $user = User::factory()->create();

        $user->delete();

        $this->assertNull(User::find($user->id));
        $this->assertNotNull(User::withTrashed()->find($user->id));
    }

    public function test_it_has_many_orders(): void
    {
        $user = User::factory()->has(Order::factory()->count(2))->create();

        $this->assertCount(2, $user->orders);
    }

    public function test_the_database_rejects_an_unknown_role(): void
    {
        $this->expectExceptionMessage('users_role_check');

        DB::table('users')->insert([
            'name' => 'X',
            'email' => 'x@example.test',
            'password' => 'x',
            'role' => 'superuser',
        ]);
    }
}
