<?php

namespace App\Models;

use App\Models\Concerns\UsesTimestampTz;
use Database\Factories\InventoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'quantity', 'low_stock_threshold'])]
class Inventory extends Model
{
    /** @use HasFactory<InventoryFactory> */
    use HasFactory, UsesTimestampTz;

    protected $attributes = [
        'quantity' => 0,
        'low_stock_threshold' => 5,
    ];

    public function isLowStock(): bool
    {
        return $this->quantity <= $this->low_stock_threshold;
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'low_stock_threshold' => 'integer',
        ];
    }
}
