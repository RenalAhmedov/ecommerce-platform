<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $total = fake()->randomFloat(2, 5, 500);

        return [
            'order_number' => 'ORD-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
            'user_id' => User::factory(),
            'status' => OrderStatus::Paid,
            'subtotal' => $total,
            'total' => $total,
            'payment_reference' => 'mock_'.Str::uuid(),
            'shipping_address' => [
                'fullName' => fake()->name(),
                'line1' => fake()->streetAddress(),
                'city' => fake()->city(),
                'postalCode' => fake()->postcode(),
                'country' => fake()->countryCode(),
            ],
            'paid_at' => now(),
        ];
    }
}
