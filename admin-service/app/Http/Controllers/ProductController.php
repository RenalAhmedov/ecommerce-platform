<?php

namespace App\Http\Controllers;

use App\Enums\ProductStatusFilter;
use App\Http\Requests\Products\ProductIndexRequest;
use App\Http\Requests\Products\StoreProductRequest;
use App\Http\Requests\Products\UpdateProductRequest;
use App\Models\Product;
use App\Services\CategoryService;
use App\Services\ProductImageService;
use App\Services\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProductController extends Controller
{
    private const PER_PAGE = 20;

    public function __construct(
        private readonly ProductService $products,
        private readonly CategoryService $categories,
        private readonly ProductImageService $images,
    ) {}

    public function index(ProductIndexRequest $request): View
    {
        return view('products.index', [
            'products' => $this->products->paginate($request->validated(), self::PER_PAGE),
            'categories' => $this->categories->options(),
            'statuses' => ProductStatusFilter::cases(),
        ]);
    }

    public function create(): View
    {
        return view('products.create', ['categories' => $this->categories->options()]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $product = $this->products->create($request->validated(), $request->user());

        return redirect()->route('products.edit', $product)->with('success', "Product {$product->sku} created.");
    }

    public function edit(Product $product): View
    {
        return view('products.edit', [
            'product' => $this->products->withDetails($product),
            'categories' => $this->categories->options(),
            'imageProcessing' => $this->images->isProcessing($product),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $product = $this->products->update($product, $request->validated());

        return redirect()->route('products.edit', $product)->with('success', 'Product saved.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->products->delete($product);

        return redirect()->route('products.index')->with('success', "Product {$product->sku} moved to trash.");
    }

    public function restore(Product $product): RedirectResponse
    {
        $this->products->restore($product);

        return redirect()->route('products.index')->with('success', "Product {$product->sku} restored.");
    }
}
