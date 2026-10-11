<?php

declare(strict_types=1);

return [
    /*
    | Your secret key, sk_test_... or sk_live_... Never commit it: set it in .env.
    */
    'secret' => env('KETAPAY_SECRET_KEY'),

    /*
    | Leave null for production (https://api.ketapay.com/v1).
    */
    'base_url' => env('KETAPAY_BASE_URL'),

    /*
    | Retries for network errors, 429 and 5xx (the same Idempotency-Key is reused), and the
    | timeout of each attempt.
    */
    'max_retries' => (int) env('KETAPAY_MAX_RETRIES', 2),
    'timeout_ms' => (int) env('KETAPAY_TIMEOUT_MS', 30000),

    /*
    | Where the SDK logs: a channel from config/logging.php (null = your default channel), and the
    | level (off, error, warn, info, debug). Keys, customer tokens and bodies are never logged.
    */
    'log_channel' => env('KETAPAY_LOG_CHANNEL'),
    'log_level' => env('KETAPAY_LOG_LEVEL', 'warn'),

    'webhook' => [
        /*
        | The endpoint's signing secret (whsec_...). While rotating, list both, comma separated:
        | KETAPAY_WEBHOOK_SECRET=whsec_new,whsec_old
        */
        'secret' => env('KETAPAY_WEBHOOK_SECRET'),

        /*
        | The URL KetaPay posts events to, relative to your app. Set it to null to register the
        | route yourself (see the README).
        */
        'path' => env('KETAPAY_WEBHOOK_PATH', 'ketapay/webhook'),

        /*
        | Extra middleware for the webhook route, for example a rate limiter. The route has no
        | session or CSRF middleware: the signature is what authenticates it.
        */
        'middleware' => [],

        /*
        | Oldest signature to accept, in seconds.
        */
        'tolerance' => 300,

        /*
        | KetaPay retries failed deliveries for 3 days and may deliver an event twice. Event ids
        | seen within this many seconds are acknowledged without dispatching again. 0 turns it off.
        */
        'dedupe_seconds' => 259200,
    ],
];
