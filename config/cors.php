<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'broadcasting/*'],

    'allowed_methods' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_origins' => array_filter(array_map(
        'trim',
        explode(',', env('CORS_ALLOWED_ORIGINS', env('APP_FRONTEND_URL', 'https://b2bwhitelabelota.vercel.app')))
    )),
    
    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // API authentication uses Bearer tokens, not cross-site cookies.
    'supports_credentials' => false,

];
