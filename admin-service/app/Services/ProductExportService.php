<?php

namespace App\Services;

use App\Repositories\ProductRepository;
use App\Support\ProductCsv;

class ProductExportService
{
    public function __construct(private readonly ProductRepository $products) {}

    /**
     * @param  array{search?: ?string, category_id?: int|string|null, status?: ?string}  $filters
     * @param  resource  $output
     */
    public function write(array $filters, $output): void
    {
        ProductCsv::writeHeader($output);

        foreach ($this->products->lazyFiltered($filters) as $product) {
            ProductCsv::writeProduct($output, $product);
        }
    }
}
