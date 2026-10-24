<?php

$redisConnection = fn (string $database) => [
    'url' => env('REDIS_URL'),
    'host' => env('REDIS_HOST', '127.0.0.1'),
    'username' => env('REDIS_USERNAME'),
    'password' => env('REDIS_PASSWORD'),
    'port' => env('REDIS_PORT', '6379'),
    'database' => $database,
    'max_retries' => env('REDIS_MAX_RETRIES', 3),
    'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
    'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
    'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
    'command_retries' => env('REDIS_COMMAND_RETRIES', 0),
];

return [
    'migrations' => [
        'table' => 'laravel_migrations',
        'update_date_on_publish' => true,
    ],

    // Laravel merges this block as a whole, so the framework's two connections are repeated here.
    'redis' => [
        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', 'admin:'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => $redisConnection(env('REDIS_DB', '0')),

        'cache' => $redisConnection(env('REDIS_CACHE_DB', '1')),

        // Keys owned by the Nest api-gateway (e.g. auth:revoked-token:<jti>): same DB, no "admin:" prefix.
        'shared' => [
            ...$redisConnection(env('REDIS_SHARED_DB', '0')),
            'options' => ['prefix' => ''],
        ],

        // Subscribes to the api-gateway's events (orders:listen): no prefix, and it
        // waits for messages indefinitely instead of timing out.
        'events' => [
            ...$redisConnection(env('REDIS_SHARED_DB', '0')),
            'read_timeout' => -1,
            'options' => ['prefix' => ''],
        ],
    ],
];
