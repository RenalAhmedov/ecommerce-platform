<?php

namespace Tests\Unit\Services;

use App\Enums\ImportStatus;
use App\Jobs\ImportProducts;
use App\Models\Product;
use App\Models\ProductImport;
use App\Models\User;
use App\Repositories\CategoryRepository;
use App\Repositories\ProductImportRepository;
use App\Repositories\ProductRepository;
use App\Services\ProductImportService;
use App\Services\ProductService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;
use Tests\Unit\UnitTestCase;

class ProductImportServiceTest extends UnitTestCase
{
    private const HEADER = 'sku,name,slug,description,price,category_slug,is_active,stock';

    private ProductImportRepository&MockInterface $imports;

    private ProductRepository&MockInterface $products;

    private CategoryRepository&MockInterface $categories;

    private ProductService&MockInterface $productService;

    private ProductImportService $service;

    private User $admin;

    /** @var list<string> */
    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->imports = $this->mock(ProductImportRepository::class);
        $this->products = $this->mock(ProductRepository::class);
        $this->categories = $this->mock(CategoryRepository::class);
        $this->productService = $this->mock(ProductService::class);
        $this->service = app(ProductImportService::class);
        $this->admin = User::factory()->admin()->make(['id' => 1]);
    }

    protected function tearDown(): void
    {
        array_map(unlink(...), $this->files);

        parent::tearDown();
    }

    /** @param  list<string>  $rows */
    private function csvFile(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'import');
        file_put_contents($path, implode("\n", [self::HEADER, ...$rows])."\n");

        return $this->files[] = $path;
    }

    private function queuedImport(string $path): ProductImport
    {
        $import = new ProductImport(['original_name' => 'products.csv', 'file_path' => 'product-imports/x.csv', 'total_rows' => 0]);
        $import->id = 7;
        $import->setRelation('user', $this->admin);

        $this->imports->shouldReceive('findWithUser')->with(7)->andReturn($import);
        $this->imports->shouldReceive('claim')->with(7)->andReturnTrue();
        $this->imports->shouldReceive('filePath')->with($import)->andReturn($path);
        $this->imports->shouldReceive('save')->with($import);
        $this->imports->shouldReceive('deleteFile')->once()->with($import);
        $this->categories->shouldReceive('idsBySlug')->andReturn([]);

        return $import;
    }

    public function test_start_records_the_import_and_queues_it(): void
    {
        Bus::fake();
        $file = new UploadedFile($this->csvFile(['A-1,One,,,1,,,', 'A-2,Two,,,2,,,']), 'products.csv', 'text/csv', null, true);
        $import = new ProductImport(['original_name' => 'products.csv', 'file_path' => 'x', 'total_rows' => 2]);
        $import->id = 7;
        $this->imports->shouldReceive('create')->once()->with($file, $this->admin, 2)->andReturn($import);

        $this->assertSame($import, $this->service->start($file, $this->admin));
        Bus::assertDispatched(ImportProducts::class, fn (ImportProducts $job) => $job->importId === 7);
    }

    public function test_start_refuses_empty_files_and_files_over_the_row_limit(): void
    {
        $this->imports->shouldNotReceive('create');

        foreach ([[], array_fill(0, ProductImportService::MAX_ROWS + 1, 'S,N,,,1,,,')] as $rows) {
            $file = new UploadedFile($this->csvFile($rows), 'products.csv', 'text/csv', null, true);

            try {
                $this->service->start($file, $this->admin);
                $this->fail('The file was accepted.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('file', $e->errors());
            }
        }
    }

    public function test_an_import_another_worker_already_claimed_is_skipped(): void
    {
        $this->imports->shouldReceive('findWithUser')->andReturn(new ProductImport);
        $this->imports->shouldReceive('claim')->with(7)->andReturnFalse();
        $this->imports->shouldNotReceive('filePath');
        $this->productService->shouldNotReceive('create', 'update');

        $this->service->process(7);
    }

    public function test_new_skus_are_created_and_known_skus_updated(): void
    {
        $import = $this->queuedImport($this->csvFile([
            'lamp-1,Desk Lamp,,Bright,49.90,,yes,7',
            'MUG-1,Renamed Mug,,,6.00,,0,999',
        ]));
        $existing = Product::factory()->make(['id' => 3, 'sku' => 'MUG-1']);
        $this->products->shouldReceive('findBySkuWithTrashed')->with('LAMP-1')->andReturnNull();
        $this->products->shouldReceive('findBySkuWithTrashed')->with('MUG-1')->andReturn($existing);

        $this->productService->shouldReceive('create')->once()->with([
            'sku' => 'LAMP-1', 'name' => 'Desk Lamp', 'slug' => 'desk-lamp', 'price' => '49.90',
            'description' => 'Bright', 'category_id' => null, 'is_active' => true, 'initial_stock' => '7',
        ], $this->admin);
        $this->productService->shouldReceive('update')->once()->with($existing, [
            'sku' => 'MUG-1', 'name' => 'Renamed Mug', 'price' => '6.00', 'description' => null, 'category_id' => null, 'is_active' => false,
        ]);

        $this->service->process(7);

        $this->assertSame([ImportStatus::Completed, 2, 1, 1, 0, []], [$import->status, $import->processed_rows, $import->created_count, $import->updated_count, $import->failed_count, $import->errors]);
    }

    public function test_bad_rows_are_reported_with_their_row_number_and_do_not_stop_the_import(): void
    {
        $import = $this->queuedImport($this->csvFile([
            'bad sku,Bad,,,-5,,,',
            'OK-2,Unknown category,,,1,nope,,',
            'GONE-1,Deleted,,,1,,,',
            'OK-3,Fine,,,2,,,',
        ]));
        $this->products->shouldReceive('findBySkuWithTrashed')->with('BAD SKU')->andReturnNull();
        $this->products->shouldReceive('findBySkuWithTrashed')->with('GONE-1')->andReturn(tap(Product::factory()->make(['id' => 9]))->setAttribute('deleted_at', now()));
        $this->products->shouldReceive('findBySkuWithTrashed')->with('OK-3')->andReturnNull();
        $this->productService->shouldReceive('create')->once();

        $this->service->process(7);

        $this->assertSame([4, 1, 0, 3], [$import->processed_rows, $import->created_count, $import->updated_count, $import->failed_count]);
        $this->assertSame([2, 3, 4], array_column($import->errors, 'row'));
        $this->assertSame(['Unknown category "nope".'], $import->errors[1]['messages']);
        $this->assertSame(['This SKU belongs to a deleted product. Restore it first.'], $import->errors[2]['messages']);
    }
}
