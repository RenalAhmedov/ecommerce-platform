<?php

namespace Tests\Unit\Jobs;

use App\Integrations\NestWebhookClient;
use App\Jobs\NotifyCatalogChanged;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Unit\UnitTestCase;

class NotifyCatalogChangedTest extends UnitTestCase
{
    private const KEY = 'unit-test-internal-api-key-0123456789abcdef';

    private function client(?string $url = 'http://nest.test:3000'): NestWebhookClient
    {
        return new NestWebhookClient($url, self::KEY, 5);
    }

    public function test_it_posts_the_event_with_the_api_key(): void
    {
        Http::fake(['nest.test:3000/*' => Http::response(['status' => 'processed'], 202)]);
        $job = new NotifyCatalogChanged;

        $job->handle($this->client());

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === 'http://nest.test:3000/api/internal/webhooks/catalog'
            && $request->header('X-Api-Key') === [self::KEY]
            && $request->data() === ['eventId' => $job->eventId, 'type' => 'catalog.changed', 'occurredAt' => $job->occurredAt]);
    }

    public function test_every_retry_of_a_job_sends_the_same_event_id(): void
    {
        $job = new NotifyCatalogChanged;
        $retried = unserialize(serialize($job));

        $this->assertTrue(Str::isUuid($job->eventId));
        $this->assertSame($job->eventId, $retried->eventId);
        $this->assertNotSame($job->eventId, (new NotifyCatalogChanged)->eventId);
    }

    public function test_one_job_stands_for_every_pending_change(): void
    {
        $this->assertSame('catalog-changed', (new NotifyCatalogChanged)->uniqueId());
    }

    public function test_an_error_from_nest_fails_the_attempt_so_the_queue_retries(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Missing or invalid API key'], 401)]);

        $this->expectException(RequestException::class);

        (new NotifyCatalogChanged)->handle($this->client());
    }

    public function test_nothing_is_sent_when_the_gateway_is_not_configured(): void
    {
        Http::fake();

        (new NotifyCatalogChanged)->handle($this->client(url: null));

        Http::assertNothingSent();
    }
}
