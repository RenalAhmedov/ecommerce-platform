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

/**
 * Tells the Nest api-gateway that the catalogue changed, so it drops its
 * cached product responses at once instead of after their TTL.
 *
 * Unique until processing: while one notification waits in the queue, more
 * changes are already covered by it, so a burst (e.g. an import of 2000 rows)
 * sends one webhook. Changes made while it runs queue a new one, so none is
 * missed. The event id is fixed when the job is created, so retries reuse it
 * and Nest recognises a redelivery.
 */
class NotifyCatalogChanged implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 6;

    /** @var list<int> seconds between attempts: about 10 minutes in total */
    public array $backoff = [5, 15, 60, 120, 300];

    /** Safety net: the uniqueness lock expires even if a worker dies mid-way. */
    public int $uniqueFor = 600;

    public readonly string $eventId;

    public readonly string $occurredAt;

    public function __construct()
    {
        $this->eventId = (string) Str::uuid();
        $this->occurredAt = CarbonImmutable::now()->toIso8601ZuluString('millisecond');
    }

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

    /** Nest still catches up when its cache entries expire (60 s), so nothing stays wrong for long. */
    public function failed(?Throwable $exception): void
    {
        Log::error('Catalog webhook failed after all retries', ['event_id' => $this->eventId, 'error' => $exception?->getMessage()]);
    }
}
