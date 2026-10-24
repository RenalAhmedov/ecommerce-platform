<?php

namespace Tests\Unit\Services;

use App\Models\Product;
use App\Models\User;
use App\Services\ProductRowImporter;
use App\Services\ProductService;
use Illuminate\Validation\PresenceVerifierInterface;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\MockInterface;
use Tests\Unit\UnitTestCase;

class ProductRowImporterTest extends UnitTestCase
{
    private ProductService&MockInterface $products;

    private ProductRowImporter $importer;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->products = $this->mock(ProductService::class);
        $this->importer = app(ProductRowImporter::class);
        $this->admin = User::factory()->admin()->make(['id' => 1]);
    }

    /** @param  array<string, string>  $cells */
    private function row(array $cells): array
    {
        return [...['sku' => '', 'name' => '', 'slug' => '', 'description' => '', 'price' => '', 'category_slug' => '', 'is_active' => '', 'stock' => ''], ...$cells];
    }

    // The "category exists" rule normally asks the database; this unit test answers it itself.
    private function givenCategoriesExist(): void
    {
        $verifier = Mockery::mock(PresenceVerifierInterface::class);
        $verifier->shouldReceive('setConnection');
        $verifier->shouldReceive('getCount')->andReturn(1);
        app('validator')->setPresenceVerifier($verifier);
    }

    private function expectRejected(array $row, array $categoryIds, string $field, string $message): void
    {
        try {
            $this->importer->import($row, $categoryIds, $this->admin);
            $this->fail('The row was accepted.');
        } catch (ValidationException $e) {
            $this->assertContains($message, $e->errors()[$field] ?? [], json_encode($e->errors()));
        }
    }

    public function test_a_new_sku_is_created_with_its_opening_stock(): void
    {
        $this->givenCategoriesExist();
        $this->products->shouldReceive('findBySkuWithTrashed')->with('LAMP-1')->andReturnNull();
        $this->products->shouldReceive('create')->once()->with([
            'sku' => 'LAMP-1', 'name' => 'Desk Lamp', 'slug' => 'desk-lamp', 'price' => '49.90',
            'description' => 'Bright', 'category_id' => 3, 'is_active' => true, 'initial_stock' => '7',
        ], $this->admin);

        $created = $this->importer->import(
            $this->row(['sku' => 'lamp-1', 'name' => 'Desk Lamp', 'description' => 'Bright', 'price' => '49.90', 'category_slug' => 'lamps', 'is_active' => 'yes', 'stock' => '7']),
            ['lamps' => 3],
            $this->admin,
        );

        $this->assertTrue($created);
    }

    public function test_a_known_sku_is_updated_without_touching_stock_or_an_empty_slug(): void
    {
        $existing = Product::factory()->make(['id' => 3, 'sku' => 'MUG-1']);
        $this->products->shouldReceive('findBySkuWithTrashed')->with('MUG-1')->andReturn($existing);
        $this->products->shouldReceive('update')->once()->with($existing, [
            'sku' => 'MUG-1', 'name' => 'Renamed Mug', 'price' => '6.00', 'description' => null, 'category_id' => null, 'is_active' => false,
        ]);

        $created = $this->importer->import($this->row(['sku' => 'MUG-1', 'name' => 'Renamed Mug', 'price' => '6.00', 'is_active' => '0', 'stock' => '999']), [], $this->admin);

        $this->assertFalse($created);
    }

    public function test_a_file_without_the_category_column_keeps_the_category(): void
    {
        $existing = Product::factory()->make(['id' => 3, 'sku' => 'MUG-1']);
        $this->products->shouldReceive('findBySkuWithTrashed')->andReturn($existing);
        $this->products->shouldReceive('update')->once()->withArgs(fn ($product, array $changes) => ! array_key_exists('category_id', $changes));

        $this->importer->import(['sku' => 'MUG-1', 'name' => 'Mug', 'price' => '6.00'], [], $this->admin);
    }

    public function test_an_unknown_category_is_rejected_before_any_lookup(): void
    {
        $this->products->shouldNotReceive('findBySkuWithTrashed');

        $this->expectRejected($this->row(['sku' => 'A-1', 'name' => 'A', 'price' => '1', 'category_slug' => 'nope']), [], 'category_slug', 'Unknown category "nope".');
    }

    public function test_the_sku_of_a_deleted_product_is_rejected(): void
    {
        $deleted = Product::factory()->make(['id' => 9])->setAttribute('deleted_at', now());
        $this->products->shouldReceive('findBySkuWithTrashed')->andReturn($deleted);
        $this->products->shouldNotReceive('update', 'create');

        $this->expectRejected($this->row(['sku' => 'GONE-1', 'name' => 'Gone', 'price' => '1']), [], 'sku', 'This SKU belongs to a deleted product. Restore it first.');
    }

    public function test_rows_are_validated_with_the_form_rules(): void
    {
        $this->products->shouldReceive('findBySkuWithTrashed')->andReturnNull();
        $this->products->shouldNotReceive('create');

        $this->expectRejected($this->row(['sku' => 'bad sku', 'name' => 'Bad', 'price' => '1']), [], 'sku', 'The SKU may only contain letters, numbers, hyphens and underscores.');
        $this->expectRejected($this->row(['sku' => 'OK-1', 'name' => 'Ok', 'price' => '-5']), [], 'price', 'The price field must be at least 0.');
        $this->expectRejected($this->row(['sku' => 'OK-2', 'name' => 'Ok', 'price' => '1', 'is_active' => 'maybe']), [], 'is_active', 'The is active field must be true or false.');
    }
}
