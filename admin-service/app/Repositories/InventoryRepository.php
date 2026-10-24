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

    /** @param  list<int>  $productIds */
    public function lockByProductIds(array $productIds): void
    {
        // Same order as the api-gateway, so the two services can never deadlock on stock rows.
        sort($productIds);

        Inventory::whereIn('product_id', $productIds)->orderBy('product_id')->lockForUpdate()->get();
    }

    public function adjustQuantity(int $productId, int $change): void
    {
        Inventory::where('product_id', $productId)->increment('quantity', $change);
    }

    /** @param  array{product_id: int, user_id?: ?int, order_id?: ?int, change: int, reason: mixed, note?: ?string}  $attributes */
    public function recordMovement(array $attributes): InventoryMovement
    {
        return InventoryMovement::create($attributes);
    }
}
