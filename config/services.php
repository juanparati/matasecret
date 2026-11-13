<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'cloudflare' => [
        'account_id' => env('CLOUDFLARE_ACCOUNT_ID'),
        'key' => env('CLOUDFLARE_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sign-in Provider
    |--------------------------------------------------------------------------
    |
    | Credentials of the provider selected by AUTH_PROVIDER, stored under its
    | Socialite driver key. Settings a provider does not use stay unset.
    |
    */

    (string) env('AUTH_PROVIDER', 'google') => array_filter([
        'client_id' => env('AUTH_CLIENT_ID'),
        'client_secret' => env('AUTH_CLIENT_SECRET'),
        'redirect' => rtrim((string) env('APP_URL', 'http://localhost'), '/').'/auth/callback',
        'base_url' => env('AUTH_BASE_URL'),
        'auth_server_id' => env('AUTH_AUTH_SERVER_ID'),
        'host' => env('AUTH_HOST'),
        'instance_uri' => env('AUTH_INSTANCE_URI'),
        'instance_url' => env('AUTH_INSTANCE_URL'),
        'subdomain' => env('AUTH_SUBDOMAIN'),
        'tenant' => env('AUTH_TENANT'),
        'key_id' => env('AUTH_KEY_ID'),
        'team_id' => env('AUTH_TEAM_ID'),
        'private_key' => env('AUTH_PRIVATE_KEY'),
    ], fn (mixed $value, string $key): bool => $value !== null || in_array($key, ['client_id', 'client_secret'], true), ARRAY_FILTER_USE_BOTH),

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
