<?php

namespace Tests\Feature\Products;

use App\Enums\ImportStatus;
use App\Enums\InventoryMovementReason;
use App\Jobs\ImportProducts;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductImport;
use App\Models\User;
use App\Services\ProductImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Feature\FeatureTestCase;

class ProductImportExportTest extends FeatureTestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->admin = User::factory()->admin()->create();
    }

    /** @param  list<list<string|int|null>>  $rows */
    private function csv(array $rows, array $header = ['sku', 'name', 'slug', 'description', 'price', 'category_slug', 'is_active', 'stock']): UploadedFile
    {
        $lines = array_map(fn (array $row) => implode(',', array_map(fn ($cell) => '"'.str_replace('"', '""', (string) $cell).'"', $row)), [$header, ...$rows]);

        return UploadedFile::fake()->createWithContent('products.csv', "\xEF\xBB\xBF".implode("\n", $lines)."\n");
    }

    private function import(UploadedFile $file): TestResponse
    {
        return $this->actingAs($this->admin)->post('/products/import', ['file' => $file]);
    }

    /** @return list<array<string, string>> */
    private function exportRows(string $query = ''): array
    {
        $content = $this->actingAs($this->admin)->get('/products/export'.$query)->assertOk()->streamedContent();
        $lines = array_map(str_getcsv(...), explode("\n", trim(str_replace("\xEF\xBB\xBF", '', $content))));
        $header = array_shift($lines);

        return array_map(fn (array $line) => array_combine($header, $line), $lines);
    }

    public function test_export_writes_the_filtered_products_as_csv(): void
    {
        $category = Category::factory()->create(['slug' => 'mugs']);
        $mug = Product::factory()->for($category)->create(['sku' => 'MUG-1', 'name' => 'Mug', 'price' => 9.5]);
        Inventory::factory()->for($mug)->create(['quantity' => 12]);
        Product::factory()->inactive()->create(['sku' => 'OLD-1']);

        $response = $this->actingAs($this->admin)->get('/products/export?status=active');
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringStartsWith("\xEF\xBB\xBF", $response->streamedContent());

        $this->assertSame([[
            'sku' => 'MUG-1', 'name' => 'Mug', 'slug' => $mug->slug, 'description' => $mug->description,
            'price' => '9.50', 'category_slug' => 'mugs', 'is_active' => '1', 'stock' => '12',
        ]], $this->exportRows('?status=active'));
    }

    public function test_export_neutralises_spreadsheet_formulas(): void
    {
        Product::factory()->create(['sku' => 'EVIL-1', 'name' => '=HYPERLINK("http://evil.test")', 'description' => '@SUM(1)']);

        [$row] = $this->exportRows();

        $this->assertSame("'=HYPERLINK(\"http://evil.test\")", $row['name']);
        $this->assertSame("'@SUM(1)", $row['description']);
    }

    public function test_an_upload_is_queued_with_its_row_count(): void
    {
        Queue::fake();

        $this->import($this->csv([['A-1', 'One', '', '', '1', '', '', ''], ['A-2', 'Two', '', '', '2', '', '', '']]))
            ->assertRedirect('/products/import');

        $import = ProductImport::sole();
        $this->assertSame(ImportStatus::Queued, $import->status);
        $this->assertSame(2, $import->total_rows);
        Storage::disk('local')->assertExists($import->file_path);
        Queue::assertPushed(ImportProducts::class, fn (ImportProducts $job) => $job->importId === $import->id);

        $this->actingAs($this->admin)->get('/products/import')->assertSee('products.csv')->assertSee('http-equiv="refresh"', false);
    }

    public function test_new_skus_are_created_with_opening_stock(): void
    {
        Category::factory()->create(['slug' => 'lamps']);

        $this->import($this->csv([['lamp-1', 'Desk Lamp', '', 'Bright', '49.90', 'lamps', 'yes', '7']]))->assertSessionHasNoErrors();

        $product = Product::sole();
        $this->assertSame(['LAMP-1', 'desk-lamp', '49.90', true, 7], [$product->sku, $product->slug, $product->price, $product->is_active, $product->inventory->quantity]);
        $movement = $product->inventoryMovements()->sole();
        $this->assertSame([InventoryMovementReason::Initial, $this->admin->id], [$movement->reason, $movement->user_id]);

        $import = ProductImport::sole();
        $this->assertSame([ImportStatus::Completed, 1, 1, 0, 0], [$import->status, $import->processed_rows, $import->created_count, $import->updated_count, $import->failed_count]);
        Storage::disk('local')->assertMissing($import->file_path);
    }

    public function test_existing_skus_are_updated_but_their_stock_is_not_touched(): void
    {
        $product = Product::factory()->create(['sku' => 'MUG-1', 'slug' => 'mug', 'price' => 5]);
        Inventory::factory()->for($product)->create(['quantity' => 3]);

        $this->import($this->csv([['MUG-1', 'Renamed Mug', '', '', '6.00', '', '0', '999']]));

        $product->refresh();
        $this->assertSame(['Renamed Mug', 'mug', '6.00', false, 3], [$product->name, $product->slug, $product->price, $product->is_active, $product->inventory->quantity]);
        $this->assertSame(1, ProductImport::sole()->updated_count);
    }

    public function test_bad_rows_are_reported_and_the_others_still_imported(): void
    {
        Product::factory()->create(['sku' => 'GONE-1'])->delete();

        $this->import($this->csv([
            ['OK-1', 'Fine', '', '', '1', '', '', ''],
            ['bad sku', 'Bad', '', '', '-5', '', '', ''],
            ['OK-2', 'Unknown category', '', '', '1', 'nope', '', ''],
            ['GONE-1', 'Deleted', '', '', '1', '', '', ''],
            ['OK-3', 'Also fine', '', '', '2', '', 'maybe', ''],
        ]));

        $import = ProductImport::sole();
        $this->assertSame([5, 1, 0, 4], [$import->processed_rows, $import->created_count, $import->updated_count, $import->failed_count]);
        $this->assertSame([3, 4, 5, 6], array_column($import->errors, 'row'));
        $this->assertStringContainsString('SKU may only contain', implode(' ', $import->errors[0]['messages']));
        $this->assertSame(['Unknown category "nope".'], $import->errors[1]['messages']);
        $this->assertSame(['This SKU belongs to a deleted product. Restore it first.'], $import->errors[2]['messages']);
        $this->assertSame(['OK-1'], Product::pluck('sku')->all());
    }

    public function test_importing_the_same_file_twice_creates_nothing_new(): void
    {
        $file = [['TWICE-1', 'Twice', '', '', '1', '', '', '5']];

        $this->import($this->csv($file));
        $this->import($this->csv($file));

        $this->assertSame(1, Product::count());
        $this->assertSame(5, Product::sole()->inventory->quantity);
        $this->assertSame([1, 1], ProductImport::orderBy('id')->get()->map(fn ($import) => $import->created_count + $import->updated_count)->all());
    }

    public function test_an_exported_file_can_be_imported_back(): void
    {
        $category = Category::factory()->create();
        Product::factory()->count(3)->for($category)->create();
        $content = $this->actingAs($this->admin)->get('/products/export')->streamedContent();

        $this->import(UploadedFile::fake()->createWithContent('export.csv', $content))->assertSessionHasNoErrors();

        $import = ProductImport::sole();
        $this->assertSame([3, 0, 3, 0], [$import->total_rows, $import->created_count, $import->updated_count, $import->failed_count]);
    }

    public function test_files_with_a_wrong_header_too_many_rows_or_no_rows_are_rejected(): void
    {
        $this->import($this->csv([['X', '1']], header: ['sku', 'price']))
            ->assertSessionHasErrors(['file' => 'The CSV header is missing: name. Expected columns: sku, name, slug, description, price, category_slug, is_active, stock.']);

        $this->import($this->csv([]))->assertSessionHasErrors(['file' => 'The file has no product rows.']);

        $rows = array_fill(0, 2001, ['S', 'N', '', '', '1', '', '', '']);
        $this->import($this->csv($rows))->assertSessionHasErrors(['file' => 'The file has 2001 rows; the limit is 2000 per import.']);

        $this->assertSame(0, ProductImport::count());
    }

    public function test_a_job_delivered_twice_only_imports_once(): void
    {
        Queue::fake();
        $this->import($this->csv([['ONCE-1', 'Once', '', '', '1', '', '', '']]));
        $import = ProductImport::sole();

        $job = new ImportProducts($import->id);
        $job->handle(app(ProductImportService::class));
        $job->handle(app(ProductImportService::class));

        $this->assertSame(1, $import->refresh()->created_count);
    }
}
