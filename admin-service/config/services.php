<?php

return [
    'nest' => [
        // The api-gateway, inside Docker http://api-gateway:3000: its catalogue webhook and,
        // for the shop search preview, its GraphQL API. Empty (as in tests) turns both off.
        'url' => env('NEST_API_URL'),
        // Must equal INTERNAL_API_KEY in api-gateway/.env.
        'internal_api_key' => env('INTERNAL_API_KEY'),
        'timeout_seconds' => 5,
    ],
];
