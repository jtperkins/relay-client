<?php

declare(strict_types=1);

/*
 * The study messaging relay (docs/relay.md in the relay's repository).
 *
 * The client is on when base_url and token are both set. The signing secrets are the project's
 * callback secret (`artisan project:callback`) and, during a rotation's overlap, the previous one.
 */
return [
    // The project's API Client URL, with /api/v1 and no trailing slash.
    'base_url' => env('RELAY_BASE_URL'),

    // A project API token (msg_live_…).
    'token' => env('RELAY_TOKEN'),

    'callback_secret' => env('RELAY_CALLBACK_SECRET'),
    'callback_secret_previous' => env('RELAY_CALLBACK_SECRET_PREVIOUS'),

    // Seconds. No HTTP retry is built in: retries belong to the caller's queue.
    'timeout' => (int) env('RELAY_TIMEOUT', 10),
    'connect_timeout' => (int) env('RELAY_CONNECT_TIMEOUT', 5),

    // How far a callback's signed timestamp may be from this app's clock, in seconds.
    'signature_tolerance' => 300,

    'callback' => [
        // The throttle middleware's argument ("{attempts},{minutes}") after the signature check.
        'throttle' => '1000,1',
    ],
];
