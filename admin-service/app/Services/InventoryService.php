<?php

namespace App\Services;

use App\Enums\InventoryMovementReason;
use App\Models\User;
use App\Repositories\InventoryRepository;

class InventoryService
{
    public function __construct(private readonly InventoryRepository $inventory) {}

    /** Every product gets an inventory row; opening stock is recorded as an "initial" movement. */
    public function initializeStock(int $productId, int $quantity, User $user): void
    {
        $this->inventory->create($productId, $quantity);

        if ($quantity > 0) {
            $this->inventory->recordMovement([
                'product_id' => $productId,
                'user_id' => $user->id,
                'change' => $quantity,
                'reason' => InventoryMovementReason::Initial,
            ]);
        }
    }
}
