<?php

namespace App\Jobs;

use App\Services\ProductImageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Intervention\Image\Exceptions\ImageException;
use Throwable;

class ProcessProductImage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> seconds to wait before the 2nd and 3rd attempt */
    public array $backoff = [10, 60];

    public function __construct(
        public readonly int $productId,
        public readonly string $uploadPath,
    ) {}

    public function handle(ProductImageService $images): void
    {
        try {
            $images->process($this->productId, $this->uploadPath);
        } catch (ImageException $e) {
            // A broken file will not get better on retry, so give up at once.
            $this->fail($e);
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(ProductImageService::class)->discard($this->productId, $this->uploadPath);
    }
}
