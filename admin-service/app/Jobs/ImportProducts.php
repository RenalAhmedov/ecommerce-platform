<?php

namespace App\Jobs;

use App\Services\ProductImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ImportProducts implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    // Below the Redis queue's retry_after (90 s), so a slow import is never handed to a second worker.
    public int $timeout = 85;

    public function __construct(public readonly int $importId) {}

    public function handle(ProductImportService $imports): void
    {
        $imports->process($this->importId);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Product import failed', ['import_id' => $this->importId, 'error' => $exception?->getMessage()]);

        app(ProductImportService::class)->fail($this->importId, 'The import stopped unexpectedly. Rows before the failure were saved; fix the file and import it again.');
    }
}
