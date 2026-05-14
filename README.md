# Shwanix Mailer for Laravel

Send Laravel mail through the **Shwanix HTTP mail API** (JSON `POST`) instead of SMTP. Works with standard `Mail` facades, Mailables, and queues.

**Requirements:** PHP `^8.0`, Guzzle `^7.5`, Laravel **`^7.30` through `^12.0`** (see below).

**Laravel & PHP:** Supports **Laravel 7.30+** through **12.x** (Composer cannot install this package on Laravel 7.0–7.29). Laravel **7** requires **PHP 8** via **7.30.x** for use with this package. Laravel **11+** needs PHP **^8.2** (framework requirement). Internally, **Laravel 7–8** use a SwiftMailer transport (`legacy/SwiftShwanixTransport.php`); **Laravel 9+** use Symfony Mailer (`ApiTransport`).

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

This creates `config/shwanix-mail.php` (endpoint URL, API key, HTTP timeouts, SSL verification).

## What `MAIL_MAILER=shwanix` does

Laravel’s mail stack is driven by `config/mail.php`. The **`mailer`** (sometimes called “driver” in docs) decides **how** messages are sent: `smtp`, `log`, `array`, etc.

Setting **`MAIL_MAILER=shwanix`** tells Laravel to use this package’s custom transport. The provider registers it with `Mail::extend('shwanix', …)`, so the name **`shwanix`** must appear in your mailers config (see below). You can still use other mailers (`smtp`, `log`) for different messages via `Mail::mailer('smtp')`, etc.

## Configuration

### 1. Environment (`.env`)

Required:

```env
MAIL_MAILER=shwanix
SHWANIX_MAIL_KEY=your-plain-secret
```

`SHWANIX_MAIL_KEY` is **required**. Use the plain secret registered for your app (validated server-side). It is sent as the **`api_key`** field in JSON (no attachments) or in **multipart/form-data** when files are attached.

Optional tuning (defaults are set in `config/shwanix-mail.php`):

```env
SHWANIX_MAIL_TIMEOUT=30
SHWANIX_MAIL_CONNECT_TIMEOUT=10
SHWANIX_MAIL_VERIFY_SSL=true
```

The default API URL is defined in the published `config/shwanix-mail.php` file. Set `SHWANIX_MAIL_URL` in `.env` only when you need to override that value.

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

Use Laravel’s mail API as usual. Ensure **`SHWANIX_MAIL_KEY`** is set in `.env` so requests authenticate with the Shwanix API.

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
| Recipients | **`to`**: one string (comma-separated if multiple). **`cc` / `bcc`**: omitted when empty; otherwise a **string** for one address or a **JSON array** for several (matches the Shwanix API). Logs include **`recipient_count`**. |
| Body | Prefers HTML; otherwise plain text. |
| Attachments | With attachments: **`multipart/form-data`** with `attachments[]` file parts (decoded from Symfony/Swift parts). Without attachments: **`application/json`** with optional `attachments` as base64 objects. |
| Success | `info` log with `recipient_count` and HTTP status. |
| Failure | Non-2xx HTTP, Guzzle errors, or JSON `{ "status": false, "message": "..." }` → `TransportException` and `error` logs. |

### HTTP payload

- **No attachments:** `POST` as **`application/json`** with `api_key`, `to`, `subject`, `body`, and optional `cc` / `bcc` (string or array of emails per the API). No `attachments` key when there are none.
- **With attachments:** `POST` as **`multipart/form-data`**: `api_key`, `to`, `subject`, `body`, optional `cc` / `bcc` (string or repeated `cc[]` / `bcc[]` parts), and binary **`attachments[]`** parts (filename + Content-Type from the mail part).

Responses are still treated as JSON when the server returns a JSON body (e.g. `{ "status": true }`).

## Implementation note (transport base class)

- **Laravel 9+:** `MailManager` uses Symfony Mailer. `ApiTransport` extends `Symfony\Component\Mailer\Transport\AbstractTransport`, like Laravel’s SES transport.
- **Laravel 7–8:** Mail uses SwiftMailer. A dedicated `SwiftShwanixTransport` extends `Illuminate\Mail\Transport\Transport` and is loaded only on those versions (see `legacy/SwiftShwanixTransport.php`).

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
