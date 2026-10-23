<?php

namespace App\Http\Controllers;

use App\Http\Requests\Products\UploadProductImageRequest;
use App\Models\Product;
use App\Services\ProductImageService;
use Illuminate\Http\RedirectResponse;

class ProductImageController extends Controller
{
    public function __construct(private readonly ProductImageService $images) {}

    public function store(UploadProductImageRequest $request, Product $product): RedirectResponse
    {
        $this->images->upload($product, $request->file('image'));

        return redirect()->route('products.edit', $product)->with('success', 'Image uploaded. It is being processed and will appear in a moment.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->images->remove($product);

        return redirect()->route('products.edit', $product)->with('success', 'Image removed.');
    }
}
