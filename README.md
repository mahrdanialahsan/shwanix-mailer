# Shwanix Mailer for Laravel

Send Laravel mail through the **Shwanix HTTP mail API** (JSON `POST`) instead of SMTP. Works with standard `Mail` facades, Mailables, and queues.

**Requirements:** PHP `^8.0` (Laravel 11+ needs PHP `^8.2`), Laravel `^8.83 | ^9 | ^10 | ^11 | ^12`, Guzzle `^7.5`.

## Installation

From your Laravel application root:

```bash
composer require mahrdanial/shwanix-mailer
```

The service provider is **auto-discovered**; you do not need to register it manually.

Publish the optional configuration file:

```bash
php artisan vendor:publish --tag=shwanix-mailer-config
```

This creates `config/shwanix-mail.php` (endpoint URL, HTTP timeouts, SSL verification).

## What `MAIL_MAILER=shwanix` does

Laravel’s mail stack is driven by `config/mail.php`. The **`mailer`** (sometimes called “driver” in docs) decides **how** messages are sent: `smtp`, `log`, `array`, etc.

Setting **`MAIL_MAILER=shwanix`** tells Laravel to use this package’s custom transport. The provider registers it with `Mail::extend('shwanix', …)`, so the name **`shwanix`** must appear in your mailers config (see below). You can still use other mailers (`smtp`, `log`) for different messages via `Mail::mailer('smtp')`, etc.

## Configuration

### 1. Environment (`.env`)

Minimum:

```env
MAIL_MAILER=shwanix
```

Optional tuning (defaults are set in `config/shwanix-mail.php`):

```env
SHWANIX_MAIL_TIMEOUT=30
SHWANIX_MAIL_CONNECT_TIMEOUT=10
SHWANIX_MAIL_VERIFY_SSL=true
```

The send endpoint defaults to **`https://send-mail.shwanix.com/send-mail.php`**. Override only if needed:

```env
SHWANIX_MAIL_URL=https://send-mail.shwanix.com/send-mail.php
```

### 2. Mail config (`config/mail.php`)

Register the `shwanix` mailer under `mailers`:

```php
'mailers' => [
    // ...
    'shwanix' => [
        'transport' => 'shwanix',
    ],
],
```

To use Shwanix for **all** outgoing mail by default:

```php
'default' => env('MAIL_MAILER', 'shwanix'),
```

## Usage

Use Laravel’s mail API as usual; no API key is required for the default endpoint.

```php
use Illuminate\Support\Facades\Mail;

Mail::raw('Hello from Shwanix.', function ($message) {
    $message->to('user@example.com')
        ->subject('Test');
});

Mail::send('emails.welcome', $data, function ($message) {
    $message->to(['a@example.com', 'b@example.com'])
        ->cc('cc@example.com')
        ->subject('Welcome');
});
```

Explicit mailer:

```php
Mail::mailer('shwanix')->send(...);
```

## Behaviour

| Feature | Behaviour |
|--------|-----------|
| Recipients | Single request with `to`, `cc`, `bcc` arrays (unique addresses); logs include `recipient_count`. |
| Body | Prefers HTML; otherwise plain text. |
| Attachments | Symfony `DataPart` attachments encoded as base64 in JSON. |
| Success | `info` log with `recipient_count` and HTTP status. |
| Failure | Non-2xx HTTP, Guzzle errors, or JSON `{ "status": false, "message": "..." }` → `TransportException` and `error` logs. |

### HTTP payload

One `POST` with JSON body:

- **Headers:** `Content-Type: application/json`, `Accept: application/json`
- **Fields:** `to`, `cc`, `bcc`, `subject`, `body`, `attachments` (`filename`, `mime`, `content` base64)

## Implementation note (transport base class)

Laravel’s `MailManager` expects a **Symfony Mailer** `TransportInterface`. This package’s `ApiTransport` extends `Symfony\Component\Mailer\Transport\AbstractTransport`, the same pattern Laravel uses for built-in drivers such as SES (`Illuminate\Mail\Transport\SesTransport`). There is no separate `Illuminate\Mail\Transport\Transport` base class in the framework.

## Releasing

Tag a stable version so Composer can resolve a default release:

```bash
git tag v1.0.0
git push origin v1.0.0
```

Then install with:

```bash
composer require mahrdanial/shwanix-mailer
```

## License

MIT.
