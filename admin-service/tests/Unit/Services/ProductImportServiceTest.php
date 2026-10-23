<?php

namespace Tests\Unit\Services;

use App\Enums\ImportStatus;
use App\Jobs\ImportProducts;
use App\Models\ProductImport;
use App\Models\User;
use App\Repositories\ProductImportRepository;
use App\Services\CategoryService;
use App\Services\ProductImportService;
use App\Services\ProductRowImporter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;
use Tests\Unit\UnitTestCase;

class ProductImportServiceTest extends UnitTestCase
{
    private const HEADER = 'sku,name,slug,description,price,category_slug,is_active,stock';

    private ProductImportRepository&MockInterface $imports;

    private ProductRowImporter&MockInterface $rows;

    private CategoryService&MockInterface $categories;

    private ProductImportService $service;

    private User $admin;

    /** @var list<string> */
    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->imports = $this->mock(ProductImportRepository::class);
        $this->rows = $this->mock(ProductRowImporter::class);
        $this->categories = $this->mock(CategoryService::class);
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
        $this->categories->shouldReceive('idsBySlug')->once()->andReturn(['lamps' => 3]);

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
        $this->rows->shouldNotReceive('import');

        $this->service->process(7);
    }

    public function test_every_row_is_imported_and_counted_and_bad_rows_are_reported(): void
    {
        $import = $this->queuedImport($this->csvFile(['NEW-1,New,,,1,,,', 'OLD-1,Old,,,1,,,', 'BAD-1,Bad,,,-1,,,']));
        $this->rows->shouldReceive('import')->times(3)->andReturnUsing(fn (array $row) => match ($row['sku']) {
            'NEW-1' => true,
            'OLD-1' => false,
            'BAD-1' => throw ValidationException::withMessages(['price' => 'The price field must be at least 0.']),
        });

        $this->service->process(7);

        $this->assertSame([ImportStatus::Completed, 3, 1, 1, 1], [$import->status, $import->processed_rows, $import->created_count, $import->updated_count, $import->failed_count]);
        $this->assertSame([['row' => 4, 'sku' => 'BAD-1', 'messages' => ['The price field must be at least 0.']]], $import->errors);
        $this->assertNotNull($import->finished_at);
    }
}
