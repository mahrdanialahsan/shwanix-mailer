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
    | API key
    |--------------------------------------------------------------------------
    |
    | Plain secret (e.g. SHWANIX_MAIL_KEY). Sent as JSON field "api_key", or as
    | multipart form field "api_key" when the message includes attachments.
    | The API may also accept header X-API-Key for manual calls; this package
    | uses the body/form field only.
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
