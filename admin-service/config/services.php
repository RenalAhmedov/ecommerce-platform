<?php

return [
    // The Nest api-gateway: told about catalogue changes through its internal webhook.
    'nest' => [
        // Empty disables the webhook (e.g. in tests). In Docker the gateway runs on the host
        // during development (http://host.docker.internal:3000), later as a compose service.
        'url' => env('NEST_API_URL'),
        // Must equal INTERNAL_API_KEY in api-gateway/.env.
        'internal_api_key' => env('INTERNAL_API_KEY'),
        'timeout_seconds' => 5,
    ],
];
