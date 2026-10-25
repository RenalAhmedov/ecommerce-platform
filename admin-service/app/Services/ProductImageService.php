<?php

namespace App\Services;

use App\Jobs\ProcessProductImage;
use App\Models\Product;
use App\Repositories\ProductImageRepository;
use App\Repositories\ProductRepository;
use App\Support\ImageResizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Intervention\Image\Exceptions\ImageException;

class ProductImageService
{
    private const IMAGE_SIZE = 1200;

    private const THUMBNAIL_SIZE = 300;

    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductImageRepository $images,
        private readonly ImageResizer $resizer,
        private readonly CatalogNotifier $catalog,
    ) {}

    public function upload(Product $product, UploadedFile $file): void
    {
        $uploadPath = $this->images->storeUpload($file);
        $this->images->markPending($product->id, $uploadPath);

        ProcessProductImage::dispatch($product->id, $uploadPath);

        Log::info('Product image upload queued', ['product_id' => $product->id, 'upload' => $uploadPath]);
    }

    public function isProcessing(Product $product): bool
    {
        return $this->images->pendingUpload($product->id) !== null;
    }

    /** @throws ImageException when the upload is not a readable image */
    public function process(int $productId, string $uploadPath): void
    {
        $original = $this->images->readUpload($uploadPath);

        if ($original === null || ! $this->isNewestUpload($productId, $uploadPath)) {
            $this->images->deleteUpload($uploadPath);
            Log::info('Product image upload skipped: replaced by a newer upload or already removed', ['product_id' => $productId, 'upload' => $uploadPath]);

            return;
        }

        $newPath = $this->images->storeImage(
            $productId,
            $this->resizer->fitAsWebp($original, self::IMAGE_SIZE),
            $this->resizer->squareAsWebp($original, self::THUMBNAIL_SIZE),
        );

        $obsoletePath = DB::transaction(function () use ($productId, $uploadPath, $newPath) {
            $product = $this->products->lockByIdWithTrashed($productId);

            if ($product === null || ! $this->isNewestUpload($productId, $uploadPath)) {
                return $newPath;
            }

            $previousPath = $product->image_path;
            $product->image_path = $newPath;
            $this->products->save($product);
            $this->images->clearPending($productId);

            return $previousPath;
        });

        if ($obsoletePath !== null) {
            $this->images->deleteImage($obsoletePath);
        }
        $this->images->deleteUpload($uploadPath);

        if ($obsoletePath === $newPath) {
            Log::info('Product image discarded: product gone or a newer upload won', ['product_id' => $productId, 'image' => $newPath]);

            return;
        }

        Log::info('Product image processed', ['product_id' => $productId, 'image' => $newPath]);
        $this->catalog->catalogChanged('product image changed');
    }

    public function remove(Product $product): Product
    {
        $this->images->clearPending($product->id);

        [$product, $previousPath] = DB::transaction(function () use ($product) {
            $product = $this->products->lockById($product->id);
            $previousPath = $product->image_path;
            $product->image_path = null;
            $this->products->save($product);

            return [$product, $previousPath];
        });

        if ($previousPath !== null) {
            $this->images->deleteImage($previousPath);
        }

        Log::info('Product image removed', ['product_id' => $product->id]);
        $this->catalog->catalogChanged('product image removed');

        return $product;
    }

    public function discard(int $productId, string $uploadPath): void
    {
        if ($this->isNewestUpload($productId, $uploadPath)) {
            $this->images->clearPending($productId);
        }
        $this->images->deleteUpload($uploadPath);

        Log::warning('Product image upload discarded after failing', ['product_id' => $productId, 'upload' => $uploadPath]);
    }

    // Jobs can finish out of order: only the newest upload may replace the picture.
    private function isNewestUpload(int $productId, string $uploadPath): bool
    {
        return $this->images->pendingUpload($productId) === $uploadPath;
    }
}
