<?php

namespace Danial\ShwanixMailer;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

/**
 * Shared JSON POST to the Shwanix endpoint. Throws \RuntimeException on failure.
 */
final class ShwanixApiClient
{
    /**
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
            $headers = [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ];

            $body = $payload;

            if ($apiKey !== '') {
                $headers['X-API-Key'] = $apiKey;
                $headers['API-Key'] = $apiKey;
                // Matches Shwanix Mail API: plain secret in JSON (validated against bcrypt server-side),
                // or auth via X-API-Key / API-Key headers only.
                $body = array_merge(['api_key' => $apiKey], $body);
            }

            $response = $client->request('POST', $url, [
                'headers' => $headers,
                'json' => $body,
                'http_errors' => false,
                'timeout' => $timeout,
                'connect_timeout' => $connectTimeout,
                'verify' => $verifySsl,
            ]);

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
        } catch (GuzzleException $e) {
            Log::error('Shwanix Mail API request failed', [
                'exception' => $e->getMessage(),
                'recipient_count' => $recipientCount,
            ]);

            throw new \RuntimeException('Shwanix Mail API: '.$e->getMessage(), 0, $e);
        }
    }
}
