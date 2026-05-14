<?php

namespace Danial\ShwanixMailer;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;

/**
 * POST to the Shwanix endpoint: JSON when there are no attachments, multipart/form-data when there are.
 */
final class ShwanixApiClient
{
    /**
     * @param  array<string, mixed>  $payload  Keys: to, subject, body; optional cc, bcc, attachments
     * @throws \RuntimeException
     */
    public static function send(
        ClientInterface $client,
        string $url,
        array $payload,
        int $recipientCount,
        int $timeout,
        int $connectTimeout,
        bool $verifySsl,
        string $apiKey = ''
    ): void {
        try {
            $attachments = isset($payload['attachments']) && is_array($payload['attachments'])
                ? $payload['attachments']
                : [];
            $hasAttachments = $attachments !== [];

            $base = $payload;
            unset($base['attachments']);

            if ($hasAttachments) {
                $response = self::postMultipart(
                    $client,
                    $url,
                    $base,
                    $attachments,
                    $timeout,
                    $connectTimeout,
                    $verifySsl,
                    $apiKey
                );
            } else {
                $response = self::postJson(
                    $client,
                    $url,
                    $base,
                    $timeout,
                    $connectTimeout,
                    $verifySsl,
                    $apiKey
                );
            }

            self::assertSuccessfulResponse($response, $recipientCount);
        } catch (GuzzleException $e) {
            Log::error('Shwanix Mail API request failed', [
                'exception' => $e->getMessage(),
                'recipient_count' => $recipientCount,
            ]);

            throw new \RuntimeException('Shwanix Mail API: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private static function postJson(
        ClientInterface $client,
        string $url,
        array $body,
        int $timeout,
        int $connectTimeout,
        bool $verifySsl,
        string $apiKey
    ): ResponseInterface {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if ($apiKey !== '') {
            $body = array_merge(['api_key' => $apiKey], $body);
        }

        return $client->request('POST', $url, [
            'headers' => $headers,
            'json' => $body,
            'http_errors' => false,
            'timeout' => $timeout,
            'connect_timeout' => $connectTimeout,
            'verify' => $verifySsl,
        ]);
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  list<array{filename: string, mime: string, content: string}>  $attachments
     */
    private static function postMultipart(
        ClientInterface $client,
        string $url,
        array $base,
        array $attachments,
        int $timeout,
        int $connectTimeout,
        bool $verifySsl,
        string $apiKey
    ): ResponseInterface {
        $multipart = [];

        if ($apiKey !== '') {
            $multipart[] = [
                'name' => 'api_key',
                'contents' => $apiKey,
            ];
        }

        foreach (['to', 'subject', 'body'] as $key) {
            if (! array_key_exists($key, $base)) {
                continue;
            }
            $multipart[] = [
                'name' => $key,
                'contents' => (string) $base[$key],
            ];
        }

        foreach (['cc', 'bcc'] as $key) {
            if (! array_key_exists($key, $base) || $base[$key] === null) {
                continue;
            }
            $value = $base[$key];
            if (is_array($value)) {
                $field = $key === 'cc' ? 'cc[]' : 'bcc[]';
                foreach ($value as $addr) {
                    $multipart[] = [
                        'name' => $field,
                        'contents' => (string) $addr,
                    ];
                }
            } else {
                $multipart[] = [
                    'name' => $key,
                    'contents' => (string) $value,
                ];
            }
        }

        foreach ($attachments as $att) {
            if (! is_array($att)) {
                continue;
            }
            $encoded = (string) ($att['content'] ?? '');
            $raw = base64_decode($encoded, true);
            if ($raw === false) {
                throw new \RuntimeException('Shwanix Mail API: invalid base64 in attachment '.($att['filename'] ?? ''));
            }
            $filename = (string) ($att['filename'] ?? 'attachment');
            $mime = (string) ($att['mime'] ?? 'application/octet-stream');
            $multipart[] = [
                'name' => 'attachments[]',
                'contents' => $raw,
                'filename' => $filename,
                'headers' => [
                    'Content-Type' => $mime,
                ],
            ];
        }

        return $client->request('POST', $url, [
            'headers' => [
                'Accept' => 'application/json',
            ],
            'multipart' => $multipart,
            'http_errors' => false,
            'timeout' => $timeout,
            'connect_timeout' => $connectTimeout,
            'verify' => $verifySsl,
        ]);
    }

    private static function assertSuccessfulResponse(ResponseInterface $response, int $recipientCount): void
    {
        $statusCode = $response->getStatusCode();
        $responseBody = (string) $response->getBody();

        if ($statusCode < 200 || $statusCode >= 300) {
            Log::error('Shwanix Mail API HTTP error', [
                'status' => $statusCode,
                'body' => $responseBody,
                'recipient_count' => $recipientCount,
            ]);

            throw new \RuntimeException(
                sprintf('Shwanix Mail API failed with HTTP %d: %s', $statusCode, $responseBody)
            );
        }

        if ($responseBody !== '') {
            $decoded = json_decode($responseBody, true);
            if (is_array($decoded) && array_key_exists('status', $decoded) && $decoded['status'] === false) {
                $apiMessage = isset($decoded['message']) ? (string) $decoded['message'] : 'Unknown API error';

                Log::error('Shwanix Mail API reported failure', [
                    'message' => $apiMessage,
                    'response' => $decoded,
                    'recipient_count' => $recipientCount,
                ]);

                throw new \RuntimeException('Shwanix Mail API: '.$apiMessage);
            }
        }

        Log::info('Shwanix Mail API message sent', [
            'recipient_count' => $recipientCount,
            'http_status' => $statusCode,
        ]);
    }
}
