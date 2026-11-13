<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Allowed Email Domains
    |--------------------------------------------------------------------------
    |
    | Only accounts with an email address in one of these domains may sign
    | in, and secret links may only be emailed to addresses within them.
    | Separate several domains with commas.
    |
    */

    'allowed_domains' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('AUTH_ALLOWED_DOMAINS', '')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Secret Store
    |--------------------------------------------------------------------------
    |
    | Secrets are kept in memory only. "apcu" is shared by the workers of a
    | single PHP process (Octane or one FPM pool) and cleared on restart.
    | "shmop" is shared by every pool running as the same OS user on this
    | host and survives restarts. "redis" can be shared across hosts; point
    | it at a Redis without persistence. "array" is intended for tests.
    |
    | The shmop segment reserves slots * slot_size bytes up front; the default
    | slot size fits the longest secret after both encryption layers.
    |
    | Supported: "apcu", "shmop", "redis", "array"
    |
    */

    'store' => env('SECRETS_STORE', 'apcu'),

    'shmop' => [
        'key' => (int) env('SECRETS_SHMOP_KEY', 1162889299),
        'slots' => (int) env('SECRETS_SHMOP_SLOTS', 1000),
        'slot_size' => (int) env('SECRETS_SHMOP_SLOT_SIZE', 65536),
        'lock_path' => env('SECRETS_SHMOP_LOCK_PATH', storage_path('framework/secrets-shmop.lock')),
    ],

    'redis' => [
        'connection' => env('SECRETS_REDIS_CONNECTION', 'secrets'),
    ],

    'max_stored' => (int) env('SECRETS_MAX_STORED', 10000),

    'max_length' => 5120,

    /*
    |--------------------------------------------------------------------------
    | Custom Expiration Bounds
    |--------------------------------------------------------------------------
    */

    'min_custom_expiration_seconds' => 60,

    'max_expiration_days' => 30,

    /*
    |--------------------------------------------------------------------------
    | Sharing By Email
    |--------------------------------------------------------------------------
    |
    | Turn email links off when the installation has no mailer.
    |
    */

    'email_links' => (bool) env('SECRETS_EMAIL_LINKS', true),

    'max_recipients' => 10,

    'max_message_length' => 500,

    /*
    |--------------------------------------------------------------------------
    | Reveal Behaviour
    |--------------------------------------------------------------------------
    |
    | Number of seconds a revealed secret stays visible before it is masked.
    |
    */

    'reveal_visible_seconds' => 60,

    /*
    |--------------------------------------------------------------------------
    | Rate Limits
    |--------------------------------------------------------------------------
    */

    'rate_limits' => [
        'create_per_hour' => 30,
        'reveal_per_minute' => 10,
        'email_recipients_per_hour' => 20,
    ],

];
