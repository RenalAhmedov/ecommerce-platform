<?php

namespace App\Jobs;

use App\Integrations\NestWebhookClient;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class NotifyCatalogChanged implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 6;

    /** @var list<int> seconds between attempts: about 10 minutes in total */
    public array $backoff = [5, 15, 60, 120, 300];

    // Releases the uniqueness lock even if a worker dies mid-job.
    public int $uniqueFor = 600;

    public readonly string $eventId;

    public readonly string $occurredAt;

    public function __construct()
    {
        $this->eventId = (string) Str::uuid();
        $this->occurredAt = CarbonImmutable::now()->toIso8601ZuluString('millisecond');
    }

    // One queued notification covers every change made before it runs.
    public function uniqueId(): string
    {
        return 'catalog-changed';
    }

    public function handle(NestWebhookClient $nest): void
    {
        if (! $nest->isConfigured()) {
            Log::debug('Catalog webhook skipped: NEST_API_URL or INTERNAL_API_KEY is not set');

            return;
        }

        $nest->catalogChanged($this->eventId, $this->occurredAt);

        Log::info('Catalog change sent to the api-gateway', ['event_id' => $this->eventId]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Catalog webhook failed after all retries', ['event_id' => $this->eventId, 'error' => $exception?->getMessage()]);
    }
}
