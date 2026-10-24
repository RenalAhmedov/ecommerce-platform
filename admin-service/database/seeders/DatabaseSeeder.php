<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Demo data for a fresh install: `php artisan db:seed`. Fixed data (no Faker,
 * which production images don't include) and safe to run again: anything that
 * already exists is left alone.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
            OrderSeeder::class,
        ]);
    }
}
