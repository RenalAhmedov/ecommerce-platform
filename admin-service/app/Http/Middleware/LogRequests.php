<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LogRequests
{
    private const HEADER = 'X-Request-Id';

    private const SILENT_PATHS = ['up'];

    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->requestId($request);
        Context::add('request_id', $requestId);
        $startedAt = hrtime(true);

        $response = $next($request);
        $response->headers->set(self::HEADER, $requestId);

        if (! in_array($request->path(), self::SILENT_PATHS, true)) {
            Log::info('HTTP request', [
                'method' => $request->method(),
                'path' => '/'.ltrim($request->path(), '/'),
                'status' => $response->getStatusCode(),
                'duration_ms' => round((hrtime(true) - $startedAt) / 1_000_000, 1),
                'ip' => $request->ip(),
            ]);
        }

        return $response;
    }

    private function requestId(Request $request): string
    {
        $incoming = (string) $request->headers->get(self::HEADER);

        // Only short, plain ids are taken from outside, so nobody can inject text into the logs.
        return preg_match('/^[A-Za-z0-9-]{8,64}$/', $incoming) === 1 ? $incoming : (string) Str::uuid();
    }
}
