<?php

namespace App\Http\Controllers;

use App\Http\Requests\Products\ImportProductsRequest;
use App\Services\ProductImportService;
use App\Support\ProductCsv;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProductImportController extends Controller
{
    public function __construct(private readonly ProductImportService $imports) {}

    public function index(): View
    {
        return view('products.import', [
            'imports' => $this->imports->recent(),
            'columns' => ProductCsv::COLUMNS,
            'requiredColumns' => ProductCsv::REQUIRED_COLUMNS,
            'maxRows' => ProductImportService::MAX_ROWS,
        ]);
    }

    public function store(ImportProductsRequest $request): RedirectResponse
    {
        $import = $this->imports->start($request->file('file'), $request->user());

        return redirect()->route('products.import.index')
            ->with('success', "Import of {$import->total_rows} rows queued. This page refreshes until it is done.");
    }
}
