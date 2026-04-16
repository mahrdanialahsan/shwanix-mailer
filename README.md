# Shwanix Mailer

Laravel mail transport that delivers messages through the **Shwanix HTTP mail API** (JSON `POST`) instead of SMTP.

Compatible with **Laravel 8.83+**, **9.x**, and **10.x** (uses Symfony Mailer, which ships with those releases).

## Installation

From your Laravel application root:

```bash
composer require danial/shwanix-mailer
```

If you develop this package locally, add a path repository to your app’s `composer.json`:

```json
"repositories": [
    {
        "type": "path",
        "url": "../laravel"
    }
],
"require": {
    "danial/shwanix-mailer": "*"
}
```

The package is **auto-discovered**; no manual provider registration is required.

Publish the configuration (optional but recommended):

```bash
php artisan vendor:publish --tag=shwanix-mailer-config
```

## Configuration

### Environment

Add to `.env`:

```env
MAIL_MAILER=shwanix

SHWANIX_MAIL_URL=https://your-domain.com/send-mail.php
SHWANIX_MAIL_KEY=your-api-key

# Optional
SHWANIX_MAIL_TIMEOUT=30
SHWANIX_MAIL_CONNECT_TIMEOUT=10
SHWANIX_MAIL_VERIFY_SSL=true
```

### Mail config

Register the `shwanix` mailer in `config/mail.php` under `mailers`:

```php
'shwanix' => [
    'transport' => 'shwanix',
],
```

Set the default mailer if you want all mail to use the API:

```php
'default' => env('MAIL_MAILER', 'shwanix'),
```

### Published config file

After publishing, `config/shwanix-mail.php` exposes:

| Key | Env | Purpose |
|-----|-----|---------|
| `url` | `SHWANIX_MAIL_URL` | Full URL to the send endpoint |
| `key` | `SHWANIX_MAIL_KEY` | Value for the `API-Key` header |
| `timeout` | `SHWANIX_MAIL_TIMEOUT` | Guzzle request timeout (seconds) |
| `connect_timeout` | `SHWANIX_MAIL_CONNECT_TIMEOUT` | Guzzle connect timeout (seconds) |
| `verify` | `SHWANIX_MAIL_VERIFY_SSL` | SSL verification (boolean) |

## Usage

Any standard Laravel mail flow works once `MAIL_MAILER=shwanix` (or `Mail::mailer('shwanix')`):

```php
use Illuminate\Support\Facades\Mail;

Mail::raw('Hello', function ($message) {
    $message->to('user@example.com')
        ->subject('Test');
});

Mail::send('emails.welcome', $data, function ($message) {
    $message->to(['a@example.com', 'b@example.com'])
        ->cc('cc@example.com')
        ->subject('Welcome');
});
```

### Request format

The transport sends a **single** `POST` with JSON:

- **Headers:** `API-Key`, `Content-Type: application/json`
- **Body fields:**
  - `to` — array of email strings
  - `cc` — array of email strings
  - `bcc` — array of email strings
  - `subject` — string
  - `body` — HTML if present, otherwise plain text
  - `attachments` — array of `{ filename, mime, content }` where `content` is **base64**

Your API should accept this shape (or sit behind a proxy that normalizes it).

## Behaviour

- **Multiple recipients:** All `To`, `Cc`, and `Bcc` addresses are included in one request; logs include **`recipient_count`** (unique addresses).
- **Attachments:** Symfony `DataPart` attachments are serialized as base64 in the JSON payload.
- **Failures:** Non-2xx HTTP responses, Guzzle errors, or JSON `{ "status": false, "message": "..." }` trigger `TransportException` and an **error** log entry with context.
- **Success:** An **info** log entry is written with `recipient_count` and HTTP status.

## Requirements

- PHP `^8.0`
- `guzzlehttp/guzzle` `^7.5`
- Laravel `^8.83 | ^9.0 | ^10.0`

## License

MIT.
# shwanix-mailer
