<?php

namespace App\Integrations;

use Illuminate\Container\Attributes\Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Http;

class NestShopClient
{
    public function __construct(
        #[Config('services.nest.url')] private readonly ?string $baseUrl,
        #[Config('services.nest.timeout_seconds')] private readonly int $timeoutSeconds,
    ) {}

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed> the "data" of the answer
     *
     * @throws NestUnavailableException
     */
    public function graphql(string $query, array $variables = []): array
    {
        $body = $this->send(fn (PendingRequest $http) => $http->post('/api/graphql', ['query' => $query, 'variables' => $variables]));

        if (! empty($body['errors'])) {
            throw new NestUnavailableException('GraphQL error: '.implode('; ', array_column($body['errors'], 'message')));
        }

        return $body['data'] ?? [];
    }

    /**
     * @return array{status: string, checks: array<string, string>}
     *
     * @throws NestUnavailableException
     */
    public function health(): array
    {
        return $this->send(fn (PendingRequest $http) => $http->get('/api/health'));
    }

    /** @param  callable(PendingRequest): Response  $request */
    private function send(callable $request): array
    {
        if (blank($this->baseUrl)) {
            throw new NestUnavailableException('NEST_API_URL is not set.');
        }

        try {
            return $request(Http::baseUrl(rtrim($this->baseUrl, '/'))
                ->withHeaders(array_filter(['X-Request-Id' => Context::get('request_id')]))
                ->acceptJson()
                ->timeout($this->timeoutSeconds))
                ->throw()
                ->json() ?? [];
        } catch (ConnectionException|RequestException $e) {
            throw new NestUnavailableException($e->getMessage(), previous: $e);
        }
    }
}
