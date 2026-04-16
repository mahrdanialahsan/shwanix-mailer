<?php

namespace Danial\ShwanixMailer;

use Danial\ShwanixMailer\Transport\ApiTransport;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

class ShwanixMailerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/shwanix-mail.php', 'shwanix-mail');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/shwanix-mail.php' => config_path('shwanix-mail.php'),
        ], 'shwanix-mailer-config');

        Mail::extend('shwanix', function (array $config = []) {
            $cfg = config('shwanix-mail', []);

            $url = (string) ($cfg['url'] ?? '');
            $key = (string) ($cfg['key'] ?? '');
            $timeout = (int) ($cfg['timeout'] ?? 30);
            $connectTimeout = (int) ($cfg['connect_timeout'] ?? 10);
            $verify = (bool) ($cfg['verify'] ?? true);

            $client = new Client();

            return new ApiTransport(
                $url,
                $key,
                $client,
                $timeout,
                $connectTimeout,
                $verify
            );
        });
    }
}
