<?php

namespace App\Http\Controllers;

use App\Http\Requests\Products\ProductIndexRequest;
use App\Services\ProductExportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductExportController extends Controller
{
    public function __construct(private readonly ProductExportService $export) {}

    /** Exports what the product list currently shows (same filters). */
    public function __invoke(ProductIndexRequest $request): StreamedResponse
    {
        $filters = $request->validated();

        return response()->streamDownload(function () use ($filters) {
            $output = fopen('php://output', 'w');
            $this->export->write($filters, $output);
            fclose($output);
        }, 'products-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
