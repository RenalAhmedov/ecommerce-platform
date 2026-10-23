<?php

namespace Tests\Unit\Services;

use App\Enums\ReportPeriod;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Reports\SalesReport;
use App\Repositories\ProductRepository;
use App\Services\ProductExportService;
use App\Services\ReportExportService;
use Carbon\CarbonImmutable;
use Illuminate\Support\LazyCollection;
use Tests\Unit\UnitTestCase;

class ExportServicesTest extends UnitTestCase
{
    /** @return list<string> */
    private function capture(callable $write): array
    {
        $output = fopen('php://memory', 'w+');
        $write($output);
        rewind($output);

        return explode("\n", trim(stream_get_contents($output)));
    }

    public function test_the_product_export_writes_one_csv_line_per_product(): void
    {
        $product = Product::factory()->make(['sku' => 'MUG-1', 'name' => 'Mug', 'slug' => 'mug', 'description' => '=cmd()', 'price' => '9.50', 'is_active' => false]);
        $product->setRelation('category', new Category(['slug' => 'kitchen']));
        $product->setRelation('inventory', new Inventory(['quantity' => 12]));

        $repository = $this->mock(ProductRepository::class);
        $repository->shouldReceive('lazyFiltered')->once()->with(['status' => 'inactive'])->andReturn(LazyCollection::make([$product]));

        $lines = $this->capture(fn ($output) => app(ProductExportService::class)->write(['status' => 'inactive'], $output));

        $this->assertSame([
            "\xEF\xBB\xBFsku,name,slug,description,price,category_slug,is_active,stock",
            "MUG-1,Mug,mug,'=cmd(),9.50,kitchen,0,12",
        ], $lines);
    }

    private function salesReport(): SalesReport
    {
        return new SalesReport(
            period: ReportPeriod::Week,
            from: CarbonImmutable::parse('2026-10-12'),
            to: CarbonImmutable::parse('2026-10-26'),
            rows: [
                ['period' => '2026-10-12', 'orders' => 2, 'units' => 4, 'revenue' => '60.00', 'average' => '30.00'],
                ['period' => '2026-10-19', 'orders' => 1, 'units' => 2, 'revenue' => '15.50', 'average' => '15.50'],
            ],
            totals: ['orders' => 3, 'units' => 6, 'revenue' => '75.50', 'average' => '25.17'],
            generatedAt: CarbonImmutable::parse('2026-10-23 12:00:00'),
        );
    }

    public function test_the_report_csv_has_a_line_per_period_and_a_total(): void
    {
        $lines = $this->capture(fn ($output) => app(ReportExportService::class)->writeCsv($this->salesReport(), $output));

        $this->assertSame([
            'period_start,period,orders,units,revenue,average_order_value',
            '2026-10-12,"Week of 12 Oct 2026",2,4,60.00,30.00',
            '2026-10-19,"Week of 19 Oct 2026",1,2,15.50,15.50',
            ',TOTAL,3,6,75.50,25.17',
        ], $lines);
    }

    public function test_report_files_are_named_after_the_period_and_range(): void
    {
        $this->assertSame('sales-week-2026-10-12-to-2026-10-25.pdf', app(ReportExportService::class)->filename($this->salesReport(), 'pdf'));
    }

    public function test_the_report_pdf_is_a_small_pdf_document(): void
    {
        $pdf = app(ReportExportService::class)->pdf($this->salesReport());

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertLessThan(100_000, strlen($pdf), 'fonts are subset, not embedded whole');
    }
}
