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

    'url' => env('SHWANIX_MAIL_URL', 'https://send-mail.shwanix.com/send-mail.php'),

    /*
    |--------------------------------------------------------------------------
    | API key (optional)
    |--------------------------------------------------------------------------
    |
    | Plain secret from env. When set, the client sends:
    | - JSON field "api_key" (server validates against bcrypt-stored keys), and
    | - Headers "X-API-Key" and "API-Key" (either style is accepted by the API).
    | Omit when your endpoint does not require authentication.
    |
    */

    'key' => env('SHWANIX_MAIL_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | HTTP client
    |--------------------------------------------------------------------------
    */

    'timeout' => (int) env('SHWANIX_MAIL_TIMEOUT', 30),

    'connect_timeout' => (int) env('SHWANIX_MAIL_CONNECT_TIMEOUT', 10),

    'verify' => filter_var(env('SHWANIX_MAIL_VERIFY_SSL', true), FILTER_VALIDATE_BOOL),

];
