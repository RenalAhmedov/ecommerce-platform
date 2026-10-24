<?php

namespace App\Http\Controllers;

use App\Http\Requests\Products\ShopSearchRequest;
use App\Services\CategoryService;
use App\Services\ShopSearchService;
use Illuminate\View\View;

class ShopSearchController extends Controller
{
    public function __construct(
        private readonly ShopSearchService $shopSearch,
        private readonly CategoryService $categories,
    ) {}

    public function __invoke(ShopSearchRequest $request): View
    {
        return view('shop-search.index', [
            'filters' => $filters = $request->filters(),
            'results' => $this->shopSearch->search($filters),
            'sorts' => array_keys(ShopSearchService::SORTS),
            'categories' => $this->categories->options(),
        ]);
    }
}
