<?php

return [
    'guards' => [
        'jwt' => [
            'driver' => 'jwt',
            'provider' => 'users',
        ],
    ],

    // Must equal the Nest api-gateway's JWT_SECRET: both services verify the same tokens.
    'jwt' => [
        'secret' => env('JWT_SECRET'),
    ],
];
