<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Products\ProductIndexRequest;
use App\Http\Requests\Products\StoreProductRequest;
use App\Http\Requests\Products\UpdateProductRequest;
use App\Http\Resources\ProductCollection;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ProductController extends Controller
{
    private const DEFAULT_LIMIT = 20;

    public function __construct(private readonly ProductService $products) {}

    public function index(ProductIndexRequest $request): ProductCollection
    {
        return new ProductCollection(
            $this->products->paginate($request->validated(), $request->integer('limit', self::DEFAULT_LIMIT)),
        );
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($this->products->withDetails($product));
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->products->create($request->validated(), $request->user());

        return (new ProductResource($this->products->withDetails($product)))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $product = $this->products->update($product, $request->validated());

        return new ProductResource($this->products->withDetails($product));
    }

    public function destroy(Product $product): Response
    {
        $this->products->delete($product);

        return response()->noContent();
    }

    public function restore(Product $product): ProductResource
    {
        $this->products->restore($product);

        return new ProductResource($this->products->withDetails($product));
    }
}
