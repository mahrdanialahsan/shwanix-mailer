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

        $callback = $this->usesSymfonyMailer()
            ? $this->symfonyTransportCallback()
            : $this->swiftTransportCallback();

        Mail::extend('shwanix', $callback);
    }

    /**
     * Laravel 9+ uses Symfony Mailer; Laravel 7–8 use SwiftMailer.
     */
    protected function usesSymfonyMailer(): bool
    {
        try {
            $manager = $this->app->make('mail.manager');

            return method_exists($manager, 'createSymfonyTransport');
        } catch (\Throwable $e) {
            return version_compare($this->app->version(), '9.0.0', '>=');
        }
    }

    /**
     * @return \Closure(array): \Symfony\Component\Mailer\Transport\TransportInterface
     */
    protected function symfonyTransportCallback(): \Closure
    {
        return function (array $config = []) {
            $cfg = config('shwanix-mail', []);

            $url = (string) ($cfg['url'] ?? '');
            $timeout = (int) ($cfg['timeout'] ?? 30);
            $connectTimeout = (int) ($cfg['connect_timeout'] ?? 10);
            $verify = (bool) ($cfg['verify'] ?? true);

            $client = new Client();

            return new ApiTransport(
                $url,
                $client,
                $timeout,
                $connectTimeout,
                $verify
            );
        };
    }

    /**
     * @return \Closure(array): \Swift_Transport
     */
    protected function swiftTransportCallback(): \Closure
    {
        require_once __DIR__.'/../legacy/SwiftShwanixTransport.php';

        return function (array $config = []) {
            $cfg = config('shwanix-mail', []);

            $url = (string) ($cfg['url'] ?? '');
            $timeout = (int) ($cfg['timeout'] ?? 30);
            $connectTimeout = (int) ($cfg['connect_timeout'] ?? 10);
            $verify = (bool) ($cfg['verify'] ?? true);

            $client = new Client();

            return new \Danial\ShwanixMailer\Transport\SwiftShwanixTransport(
                $url,
                $client,
                $timeout,
                $connectTimeout,
                $verify
            );
        };
    }
}
