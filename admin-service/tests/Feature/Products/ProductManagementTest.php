<?php

namespace Tests\Feature\Products;

use App\Enums\InventoryMovementReason;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\User;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_customers_cannot_reach_products(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/products')
            ->assertRedirect(route('login', absolute: false));
    }

    public function test_the_list_shows_products_with_stock_and_category(): void
    {
        $category = Category::factory()->create(['name' => 'Laptops']);
        $product = Product::factory()->for($category)->create();
        Inventory::factory()->for($product)->create(['quantity' => 3]);

        $this->actingAs($this->admin)
            ->get('/products')
            ->assertOk()
            ->assertSee($product->sku)
            ->assertSee('Laptops')
            ->assertSee('low');
    }

    public function test_the_create_and_edit_forms_render(): void
    {
        Category::factory()->create(['name' => 'Mugs']);
        $product = Product::factory()->create(['sku' => 'FORM-1']);

        $this->actingAs($this->admin)->get('/products/create')->assertOk()->assertSee('Mugs')->assertSee('Initial stock');
        $this->actingAs($this->admin)->get("/products/{$product->id}/edit")->assertOk()->assertSee('FORM-1')->assertSee('in stock');
    }

    public function test_the_list_filters_by_search_category_and_status(): void
    {
        $category = Category::factory()->create();
        $match = Product::factory()->for($category)->create(['name' => 'Blue Mug', 'sku' => 'MUG-1']);
        $otherCategory = Product::factory()->create(['name' => 'Blue Mug XL', 'sku' => 'MUG-2']);
        $inactive = Product::factory()->for($category)->inactive()->create(['name' => 'Blue Mug Old', 'sku' => 'MUG-3']);

        $this->actingAs($this->admin)
            ->get("/products?search=blue&category_id={$category->id}&status=active")
            ->assertOk()
            ->assertSee($match->sku)
            ->assertDontSee($otherCategory->sku)
            ->assertDontSee($inactive->sku);
    }

    public function test_search_treats_like_wildcards_literally(): void
    {
        Product::factory()->create(['name' => 'Plain', 'sku' => 'PLAIN-1']);

        $this->actingAs($this->admin)
            ->get('/products?search=%25')
            ->assertOk()
            ->assertDontSee('PLAIN-1');
    }

    public function test_an_admin_creates_a_product_with_stock_and_an_initial_movement(): void
    {
        $category = Category::factory()->create();

        $response = $this->actingAs($this->admin)->post('/products', [
            'name' => 'Café Crème Mug',
            'sku' => 'mug-001',
            'price' => '12.50',
            'category_id' => $category->id,
            'is_active' => '1',
            'initial_stock' => 25,
        ]);

        $product = Product::sole();
        $response->assertRedirect(route('products.edit', $product, absolute: false));
        $this->assertSame('cafe-creme-mug', $product->slug);
        $this->assertSame('MUG-001', $product->sku);
        $this->assertSame('12.50', $product->price);
        $this->assertSame(25, $product->inventory->quantity);

        $movement = $product->inventoryMovements()->sole();
        $this->assertSame(25, $movement->change);
        $this->assertSame(InventoryMovementReason::Initial, $movement->reason);
        $this->assertSame($this->admin->id, $movement->user_id);
    }

    public function test_a_product_without_stock_gets_an_empty_inventory_and_no_movement(): void
    {
        $this->actingAs($this->admin)->post('/products', [
            'name' => 'Empty',
            'sku' => 'EMPTY-1',
            'price' => '1',
        ])->assertSessionHasNoErrors();

        $product = Product::sole();
        $this->assertSame(0, $product->inventory->quantity);
        $this->assertSame(0, $product->inventoryMovements()->count());
    }

    public function test_invalid_input_is_rejected(): void
    {
        $this->actingAs($this->admin)->post('/products', [
            'name' => '!!!',
            'sku' => 'bad sku',
            'price' => '-1.999',
            'category_id' => 999,
        ])->assertSessionHasErrors(['slug', 'sku', 'price', 'category_id']);

        $this->assertSame(0, Product::withTrashed()->count());
    }

    public function test_sku_and_slug_stay_reserved_by_soft_deleted_products(): void
    {
        Product::factory()->create(['sku' => 'TAKEN-1', 'slug' => 'taken'])->delete();

        $this->actingAs($this->admin)->post('/products', [
            'name' => 'Taken',
            'sku' => 'taken-1',
            'price' => '5',
        ])->assertSessionHasErrors([
            'sku' => 'A product with this sku already exists.',
            'slug' => 'A product with this slug already exists.',
        ]);
    }

    public function test_an_admin_updates_a_product(): void
    {
        $product = Product::factory()->create(['sku' => 'OLD-1', 'slug' => 'old']);

        $this->actingAs($this->admin)->put("/products/{$product->id}", [
            'name' => 'Renamed',
            'slug' => null,
            'sku' => 'OLD-1',
            'price' => '99.99',
            'is_active' => '0',
        ])->assertRedirect(route('products.edit', $product, absolute: false));

        $product->refresh();
        $this->assertSame('Renamed', $product->name);
        $this->assertSame('renamed', $product->slug);
        $this->assertSame('99.99', $product->price);
        $this->assertFalse($product->is_active);
    }

    public function test_a_partial_update_leaves_other_fields_alone(): void
    {
        $product = Product::factory()->create(['name' => 'Keep', 'slug' => 'keep']);

        $this->actingAs($this->admin)
            ->put("/products/{$product->id}", ['price' => '3.00'])
            ->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertSame('Keep', $product->name);
        $this->assertSame('keep', $product->slug);
        $this->assertSame('3.00', $product->price);
    }

    public function test_an_update_cannot_take_another_products_sku(): void
    {
        Product::factory()->create(['sku' => 'FIRST-1']);
        $second = Product::factory()->create(['sku' => 'SECOND-1']);

        $this->actingAs($this->admin)
            ->put("/products/{$second->id}", ['sku' => 'FIRST-1'])
            ->assertSessionHasErrors('sku');

        $this->assertSame('SECOND-1', $second->refresh()->sku);
    }

    public function test_delete_moves_to_trash_and_restore_brings_it_back(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->admin)->delete("/products/{$product->id}")->assertRedirect('/products');
        $this->assertSoftDeleted($product);

        $this->actingAs($this->admin)
            ->get('/products?status=trashed')
            ->assertSee($product->sku)
            ->assertSee('Restore');

        $this->actingAs($this->admin)->patch("/products/{$product->id}/restore")->assertRedirect('/products');
        $this->assertNotSoftDeleted($product);
    }

    public function test_trashed_products_cannot_be_edited(): void
    {
        $product = Product::factory()->create();
        $product->delete();

        $this->actingAs($this->admin)->get("/products/{$product->id}/edit")->assertNotFound();
    }
}
