<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | Users authenticate with the configured Socialite provider. There is no
    | database or user provider: the signed-in user lives in the session.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sign-in Provider
    |--------------------------------------------------------------------------
    |
    | The Socialite driver people sign in with. Its credentials live in the
    | "services" configuration under the same key. See App\Enums\AuthProvider
    | for the supported drivers; "php artisan setup" configures it for you.
    |
    */

    'social_provider' => env('AUTH_PROVIDER', 'google'),

    /*
    |--------------------------------------------------------------------------
    | Test Account
    |--------------------------------------------------------------------------
    |
    | The credentials the "test" provider accepts. It signs in without a real
    | provider and refuses to run when the application is in production.
    |
    */

    'test_account' => [
        'email' => env('AUTH_TEST_EMAIL', 'secret@example.net'),
        'password' => env('AUTH_TEST_PASSWORD', 'secret'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | The "social-session" driver is registered in the AppServiceProvider and
    | resolves the signed-in user from the current session.
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'social-session',
        ],
    ],

];
