<?php

declare(strict_types=1);

return [

    'worker' => [
        'url' => env('WORKER_URL', 'http://worker:8001'),
        'token' => env('WORKER_TOKEN', ''),
        'timeout' => (int) env('WORKER_TIMEOUT', 180),
        // auto picks the LLM engine when the worker has a key, rules works offline
        'engine' => env('WORKER_ENGINE', 'auto'),
    ],

    'storage_disk' => env('DOCUMENTS_DISK', 'local'),

    'upload' => [
        'max_kb' => 20 * 1024,
        'mimes' => ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'tif', 'tiff'],
    ],

    // Fields below this confidence go to a person even when every check passes.
    'review_threshold' => (float) env('REVIEW_THRESHOLD', 0.8),

    // Rough cost of typing a document by hand, used for the "time saved" figure.
    'manual_entry' => [
        'seconds_per_document' => 240,
        'seconds_per_line' => 25,
    ],

    'base_currency' => env('BASE_CURRENCY', 'RUB'),

    // Fixed rates for the dashboard totals. Replace with a rates provider in production.
    'rates' => [
        'RUB' => 1.0,
        'USD' => 81.5,
        'EUR' => 94.2,
        'GBP' => 108.7,
        'AMD' => 0.21,
        'KZT' => 0.16,
    ],

    'mailbox' => [
        'host' => env('IMAP_HOST'),
        'port' => (int) env('IMAP_PORT', 993),
        'encryption' => env('IMAP_ENCRYPTION', 'ssl'),
        'username' => env('IMAP_USERNAME'),
        'password' => env('IMAP_PASSWORD'),
        'folder' => env('IMAP_FOLDER', 'INBOX'),
        'address' => env('IMAP_ADDRESS'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/integrations/google/callback'),
    ],

    'xero' => [
        'client_id' => env('XERO_CLIENT_ID'),
        'client_secret' => env('XERO_CLIENT_SECRET'),
        'redirect' => env('XERO_REDIRECT_URI', '/integrations/xero/callback'),
    ],

    'demo' => [
        'enabled' => (bool) env('DEMO_MODE', false),
        'owner_email' => 'owner@demo.test',
        'accountant_email' => 'accountant@demo.test',
    ],

];
