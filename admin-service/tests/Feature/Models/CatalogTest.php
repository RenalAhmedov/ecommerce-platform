<?php

namespace Tests\Feature\Models;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Database\QueryException;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    public function test_categories_nest_through_parent_and_children(): void
    {
        $electronics = Category::factory()->create();
        $keyboards = Category::factory()->create(['parent_id' => $electronics->id]);

        $this->assertTrue($keyboards->parent->is($electronics));
        $this->assertTrue($electronics->children->first()->is($keyboards));
    }

    public function test_a_category_cannot_be_its_own_parent(): void
    {
        $category = Category::factory()->create();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('categories_parent_check');

        $category->update(['parent_id' => $category->id]);
    }

    public function test_product_casts_money_to_exact_decimal_strings(): void
    {
        $product = Product::factory()->create(['price' => 19.9]);

        $this->assertSame('19.90', $product->fresh()->price);
        $this->assertTrue($product->fresh()->is_active);
    }

    public function test_product_belongs_to_a_category_and_has_one_inventory(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create();
        Inventory::factory()->for($product)->create(['quantity' => 3, 'low_stock_threshold' => 5]);

        $product->load('category', 'inventory');

        $this->assertTrue($product->category->is($category));
        $this->assertSame(3, $product->inventory->quantity);
        $this->assertTrue($product->inventory->isLowStock());
    }

    public function test_deleting_a_category_keeps_its_products(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create();

        $category->forceDelete();

        $this->assertNull($product->fresh()->category_id);
    }

    public function test_soft_deleted_products_are_hidden_but_restorable(): void
    {
        $product = Product::factory()->create();

        $product->delete();
        $this->assertNull(Product::find($product->id));

        $product->restore();
        $this->assertNotNull(Product::find($product->id));
    }

    public function test_strict_mode_blocks_lazy_loading_in_lists(): void
    {
        Product::factory()->count(2)->create();

        $this->expectException(LazyLoadingViolationException::class);

        Product::all()->first()->inventory;
    }
}
