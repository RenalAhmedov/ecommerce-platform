<?php

namespace App\Repositories;

use App\Models\Inventory;
use App\Models\InventoryMovement;

class InventoryRepository
{
    public function create(int $productId, int $quantity): Inventory
    {
        return Inventory::create(['product_id' => $productId, 'quantity' => $quantity]);
    }

    /** @param  array{product_id: int, user_id?: ?int, order_id?: ?int, change: int, reason: mixed, note?: ?string}  $attributes */
    public function recordMovement(array $attributes): InventoryMovement
    {
        return InventoryMovement::create($attributes);
    }
}
