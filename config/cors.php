<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | The application has no cross-origin consumers: every browser request
    | comes from its own origin. CORS is therefore locked down to that single
    | origin. Any other origin sees a mismatching Access-Control-Allow-Origin
    | and the browser blocks the response; credentials are never allowed.
    |
    */

    'paths' => ['*'],

    'allowed_methods' => ['GET', 'POST', 'DELETE'],

    'allowed_origins' => [env('APP_URL', 'http://localhost')],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Content-Type', 'X-Requested-With', 'X-XSRF-TOKEN'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
