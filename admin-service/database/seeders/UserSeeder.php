<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Database\Seeder;

/** One admin and eight customers; every demo account uses the same password. */
class UserSeeder extends Seeder
{
    public const PASSWORD = 'password123';

    public const ADMIN_EMAIL = 'admin@example.com';

    private const USERS = [
        ['Shop Admin', self::ADMIN_EMAIL, UserRole::Admin],
        ['Ada Lovelace', 'ada.lovelace@example.com', UserRole::Customer],
        ['Grace Hopper', 'grace.hopper@example.com', UserRole::Customer],
        ['Alan Turing', 'alan.turing@example.com', UserRole::Customer],
        ['Margaret Hamilton', 'margaret.hamilton@example.com', UserRole::Customer],
        ['Katherine Johnson', 'katherine.johnson@example.com', UserRole::Customer],
        ['Barbara Liskov', 'barbara.liskov@example.com', UserRole::Customer],
        ['Edsger Dijkstra', 'edsger.dijkstra@example.com', UserRole::Customer],
        ['Donald Knuth', 'donald.knuth@example.com', UserRole::Customer],
    ];

    public function run(UserService $users): void
    {
        foreach (self::USERS as [$name, $email, $role]) {
            if (User::withTrashed()->where('email', $email)->exists()) {
                continue;
            }

            $users->create(['name' => $name, 'email' => $email, 'password' => self::PASSWORD, 'role' => $role->value]);
        }
    }
}
