<?php

namespace App\Integrations;

use Illuminate\Container\Attributes\Config;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Http;

class NestWebhookClient
{
    public function __construct(
        #[Config('services.nest.url')] private readonly ?string $baseUrl,
        #[Config('services.nest.internal_api_key')] private readonly ?string $apiKey,
        #[Config('services.nest.timeout_seconds')] private readonly int $timeoutSeconds,
    ) {}

    public function isConfigured(): bool
    {
        return filled($this->baseUrl) && filled($this->apiKey);
    }

    /**
     * @param  string  $occurredAt  ISO-8601, UTC
     *
     * @throws RequestException when Nest answers with an error status (the job then retries)
     */
    public function catalogChanged(string $eventId, string $occurredAt): void
    {
        Http::baseUrl(rtrim((string) $this->baseUrl, '/'))
            ->withHeaders(array_filter([
                'X-Api-Key' => (string) $this->apiKey,
                'X-Request-Id' => Context::get('request_id'),
            ]))
            ->acceptJson()
            ->timeout($this->timeoutSeconds)
            ->post('/api/internal/webhooks/catalog', [
                'eventId' => $eventId,
                'type' => 'catalog.changed',
                'occurredAt' => $occurredAt,
            ])
            ->throw();
    }
}
