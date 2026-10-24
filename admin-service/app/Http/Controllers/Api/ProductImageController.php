<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Products\UploadProductImageRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ProductImageService;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ProductImageController extends Controller
{
    public function __construct(
        private readonly ProductImageService $images,
        private readonly ProductService $products,
    ) {}

    public function store(UploadProductImageRequest $request, Product $product): JsonResponse
    {
        $this->images->upload($product, $request->file('image'));

        return (new ProductResource($this->products->withDetails($product)))
            ->additional(['meta' => ['imageProcessing' => true]])
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(Product $product): ProductResource
    {
        $product = $this->images->remove($product);

        return new ProductResource($this->products->withDetails($product));
    }
}
