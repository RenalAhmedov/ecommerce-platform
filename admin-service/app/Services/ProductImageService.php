<?php

namespace App\Services;

use App\Jobs\ProcessProductImage;
use App\Models\Product;
use App\Repositories\ProductImageRepository;
use App\Repositories\ProductRepository;
use App\Support\ImageResizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Intervention\Image\Exceptions\ImageException;

class ProductImageService
{
    private const IMAGE_SIZE = 1200;

    private const THUMBNAIL_SIZE = 300;

    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductImageRepository $images,
        private readonly ImageResizer $resizer,
    ) {}

    /** Stores the original and queues the processing; the request returns straight away. */
    public function upload(Product $product, UploadedFile $file): void
    {
        $uploadPath = $this->images->storeUpload($file);
        $this->images->markPending($product->id, $uploadPath);

        ProcessProductImage::dispatch($product->id, $uploadPath);
    }

    public function isProcessing(Product $product): bool
    {
        return $this->images->pendingUpload($product->id) !== null;
    }

    /**
     * Runs on the queue worker. Only the product's newest upload is applied: it
     * is checked again under the row lock, so two jobs finishing in the wrong
     * order (several workers) can never put an older picture back.
     *
     * @throws ImageException when the upload is not a readable image
     */
    public function process(int $productId, string $uploadPath): void
    {
        $original = $this->images->readUpload($uploadPath);

        if ($original === null || ! $this->isNewestUpload($productId, $uploadPath)) {
            $this->images->deleteUpload($uploadPath);

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
    }

    /** Also cancels an upload that is still queued, so it can't bring the image back. */
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

        return $product;
    }

    /** Clean-up for a job that gave up: the upload is dropped and the product keeps its old image. */
    public function discard(int $productId, string $uploadPath): void
    {
        if ($this->isNewestUpload($productId, $uploadPath)) {
            $this->images->clearPending($productId);
        }
        $this->images->deleteUpload($uploadPath);
    }

    private function isNewestUpload(int $productId, string $uploadPath): bool
    {
        return $this->images->pendingUpload($productId) === $uploadPath;
    }
}
