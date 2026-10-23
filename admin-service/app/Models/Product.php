<?php

namespace App\Models;

use App\Models\Concerns\UsesTimestampTz;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable(['category_id', 'name', 'slug', 'sku', 'description', 'price', 'is_active'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes, UsesTimestampTz;

    /** Processed product images live on this disk, served at /storage. */
    public const IMAGE_DISK = 'public';

    protected $attributes = [
        'is_active' => true,
    ];

    /** Each image is stored as products/<id>/<uuid>.webp with a <uuid>-thumb.webp next to it. */
    public static function thumbnailPathFor(string $imagePath): string
    {
        return Str::replaceLast('.webp', '-thumb.webp', $imagePath);
    }

    /** @return Attribute<?string, never> */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn () => $this->image_path
            ? Storage::disk(self::IMAGE_DISK)->url($this->image_path)
            : null);
    }

    /** @return Attribute<?string, never> */
    protected function thumbnailUrl(): Attribute
    {
        return Attribute::get(fn () => $this->image_path
            ? Storage::disk(self::IMAGE_DISK)->url(self::thumbnailPathFor($this->image_path))
            : null);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return HasOne<Inventory, $this> */
    public function inventory(): HasOne
    {
        return $this->hasOne(Inventory::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function orderItems(): HasMany
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
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
