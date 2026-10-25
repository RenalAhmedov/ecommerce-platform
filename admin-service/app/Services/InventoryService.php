<?php

namespace App\Services;

use App\Enums\InventoryMovementReason;
use App\Models\User;
use App\Repositories\InventoryRepository;

class InventoryService
{
    public function __construct(private readonly InventoryRepository $inventory) {}

    public function initializeStock(int $productId, int $quantity, ?User $user): void
    {
        $this->inventory->create($productId, $quantity);

        if ($quantity > 0) {
            $this->inventory->recordMovement([
                'product_id' => $productId,
                'user_id' => $user?->id,
                'change' => $quantity,
                'reason' => InventoryMovementReason::Initial,
            ]);
        }
    }

    /** @param  array<int, int>  $quantities  product id => quantity */
    public function release(array $quantities, int $orderId, ?User $user): void
    {
        if ($quantities === []) {
            return;
        }

        ksort($quantities);
        $this->inventory->lockByProductIds(array_keys($quantities));

        foreach ($quantities as $productId => $quantity) {
            $this->inventory->adjustQuantity($productId, $quantity);
            $this->inventory->recordMovement([
                'product_id' => $productId,
                'order_id' => $orderId,
                'user_id' => $user?->id,
                'change' => $quantity,
                'reason' => InventoryMovementReason::Cancellation,
            ]);
        }
    }
}
