<?php

namespace App\Repositories;

use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Uploaded originals wait on the private "local" disk until the queue worker
 * has processed them; finished images go to the public disk. A cache entry
 * remembers each product's newest upload so an older job can't overwrite it.
 */
class ProductImageRepository
{
    private const UPLOAD_DISK = 'local';

    private const UPLOAD_DIRECTORY = 'product-image-uploads';

    private const PENDING_TTL_SECONDS = 3600;

    public function storeUpload(UploadedFile $file): string
    {
        return $file->storeAs(self::UPLOAD_DIRECTORY, Str::uuid().'.'.$file->extension(), self::UPLOAD_DISK);
    }

    public function readUpload(string $path): ?string
    {
        return Storage::disk(self::UPLOAD_DISK)->get($path);
    }

    public function deleteUpload(string $path): void
    {
        Storage::disk(self::UPLOAD_DISK)->delete($path);
    }

    /** @return string the main image path, e.g. products/42/<uuid>.webp */
    public function storeImage(int $productId, string $image, string $thumbnail): string
    {
        $path = "products/{$productId}/".Str::uuid().'.webp';

        Storage::disk(Product::IMAGE_DISK)->put($path, $image);
        Storage::disk(Product::IMAGE_DISK)->put(Product::thumbnailPathFor($path), $thumbnail);

        return $path;
    }

    public function deleteImage(string $path): void
    {
        Storage::disk(Product::IMAGE_DISK)->delete([$path, Product::thumbnailPathFor($path)]);
    }

    public function markPending(int $productId, string $uploadPath): void
    {
        Cache::put($this->pendingKey($productId), $uploadPath, self::PENDING_TTL_SECONDS);
    }

    public function pendingUpload(int $productId): ?string
    {
        return Cache::get($this->pendingKey($productId));
    }

    public function clearPending(int $productId): void
    {
        Cache::forget($this->pendingKey($productId));
    }

    private function pendingKey(int $productId): string
    {
        return "product-image-pending:{$productId}";
    }
}
