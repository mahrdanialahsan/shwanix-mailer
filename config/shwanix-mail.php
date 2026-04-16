<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API endpoint
    |--------------------------------------------------------------------------
    |
    | Full URL to the Shwanix send-mail endpoint (HTTPS recommended).
    |
    */

    'url' => env('SHWANIX_MAIL_URL'),

    /*
    |--------------------------------------------------------------------------
    | API key
    |--------------------------------------------------------------------------
    |
    | Value sent as the API-Key header on each request.
    |
    */

    'key' => env('SHWANIX_MAIL_KEY'),

    /*
    |--------------------------------------------------------------------------
    | HTTP client
    |--------------------------------------------------------------------------
    */

    'timeout' => (int) env('SHWANIX_MAIL_TIMEOUT', 30),

    'connect_timeout' => (int) env('SHWANIX_MAIL_CONNECT_TIMEOUT', 10),

    'verify' => filter_var(env('SHWANIX_MAIL_VERIFY_SSL', true), FILTER_VALIDATE_BOOL),

];
