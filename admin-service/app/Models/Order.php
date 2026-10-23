<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Models\Concerns\UsesTimestampTz;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['order_number', 'user_id', 'status', 'subtotal', 'total', 'payment_reference', 'shipping_address', 'paid_at'])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory, UsesTimestampTz;

    protected $attributes = [
        'status' => OrderStatus::Pending->value,
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasMany<InventoryMovement, $this> */
    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'shipping_address' => 'array',
            'paid_at' => 'datetime',
        ];
    }
}
