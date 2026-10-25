<?php

namespace Tests\Feature\Api;

use App\Jobs\ProcessProductImage;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Api\Concerns\AuthenticatesWithNestToken;
use Tests\Feature\FeatureTestCase;

class ProductApiTest extends FeatureTestCase
{
    use AuthenticatesWithNestToken;

    public function test_the_list_uses_the_nest_response_shape(): void
    {
        $category = Category::factory()->create(['name' => 'Mugs']);
        $product = Product::factory()->for($category)->create(['sku' => 'MUG-1', 'price' => 9.5]);
        Inventory::factory()->for($product)->create(['quantity' => 4]);
        Product::factory()->count(2)->create();

        $page = $this->api()->getJson('/admin/api/products?limit=2&page=1')->assertOk();

        $this->assertSame(['data', 'meta'], array_keys($page->json()));
        $this->assertSame(['page' => 1, 'limit' => 2, 'total' => 3, 'totalPages' => 2], $page->json('meta'));
        $page->assertJsonCount(2, 'data');

        $item = $this->api()->getJson('/admin/api/products?search=MUG-1')->json('data.0');

        $this->assertSame(
            ['id', 'name', 'slug', 'sku', 'description', 'price', 'imagePath', 'imageUrl', 'thumbnailUrl', 'isActive', 'category', 'stock', 'createdAt', 'updatedAt', 'deletedAt'],
            array_keys($item),
        );
        $this->assertSame('9.50', $item['price']);
        $this->assertSame(4, $item['stock']);
        $this->assertTrue($item['isActive']);
        $this->assertSame(['id' => $category->id, 'name' => 'Mugs'], $item['category']);
    }

    public function test_show_returns_one_product_and_404_for_trashed(): void
    {
        $product = Product::factory()->create(['sku' => 'SHOW-1']);
        $trashed = tap(Product::factory()->create())->delete();

        $this->api()->getJson("/admin/api/products/{$product->id}")->assertOk()->assertJsonPath('data.sku', 'SHOW-1');
        $this->api()->getJson("/admin/api/products/{$trashed->id}")->assertNotFound();
    }

    public function test_store_creates_a_product_and_returns_201(): void
    {
        $this->api()->postJson('/admin/api/products', [
            'name' => 'Desk Lamp',
            'sku' => 'lamp-1',
            'price' => 49.99,
            'is_active' => true,
            'initial_stock' => 7,
        ])
            ->assertCreated()
            ->assertJsonPath('data.sku', 'LAMP-1')
            ->assertJsonPath('data.slug', 'desk-lamp')
            ->assertJsonPath('data.stock', 7);

        $this->assertSame($this->admin->id, Product::sole()->inventoryMovements()->sole()->user_id);
    }

    public function test_validation_and_conflicts_return_422_json(): void
    {
        Product::factory()->create(['sku' => 'DUP-1']);

        $this->api()->postJson('/admin/api/products', ['name' => 'X', 'sku' => 'bad sku', 'price' => -1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sku', 'price']);

        $this->api()->postJson('/admin/api/products', ['name' => 'Dup', 'sku' => 'DUP-1', 'price' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sku' => 'A product with this sku already exists.']);
    }

    public function test_update_is_partial(): void
    {
        $product = Product::factory()->create(['name' => 'Keep', 'price' => 1]);

        $this->api()->patchJson("/admin/api/products/{$product->id}", ['price' => 2.5])
            ->assertOk()
            ->assertJsonPath('data.name', 'Keep')
            ->assertJsonPath('data.price', '2.50');
    }

    public function test_delete_returns_204_and_restore_brings_it_back(): void
    {
        $product = Product::factory()->create();

        $this->api()->deleteJson("/admin/api/products/{$product->id}")->assertNoContent();
        $this->assertSoftDeleted($product);

        $this->api()->getJson('/admin/api/products?status=trashed')->assertJsonPath('data.0.id', $product->id);

        $this->api()->patchJson("/admin/api/products/{$product->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.deletedAt', null);
        $this->assertNotSoftDeleted($product);
    }

    public function test_image_upload_returns_202_and_the_urls_appear_once_processed(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $product = Product::factory()->create();

        Queue::fake();
        $this->api()->post("/admin/api/products/{$product->id}/image", ['image' => UploadedFile::fake()->image('a.jpg')], ['Accept' => 'application/json'])
            ->assertAccepted()
            ->assertJsonPath('meta.imageProcessing', true)
            ->assertJsonPath('data.imageUrl', null);
        Queue::assertPushed(ProcessProductImage::class);
    }

    public function test_image_upload_and_removal_through_the_api(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $product = Product::factory()->create();

        $this->api()->post("/admin/api/products/{$product->id}/image", ['image' => UploadedFile::fake()->image('a.jpg')], ['Accept' => 'application/json'])
            ->assertAccepted();

        $shown = $this->api()->getJson("/admin/api/products/{$product->id}")->json('data');
        $this->assertStringEndsWith($shown['imagePath'], $shown['imageUrl']);
        $this->assertStringEndsWith('-thumb.webp', $shown['thumbnailUrl']);

        $this->api()->deleteJson("/admin/api/products/{$product->id}/image")
            ->assertOk()
            ->assertJsonPath('data.imagePath', null)
            ->assertJsonPath('data.imageUrl', null);
    }
}
