<?php

namespace Danial\ShwanixMailer\Transport;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

/**
 * Sends mail by POSTing JSON to the Shwanix HTTP API.
 *
 * Uses Symfony Mailer's transport contract (Laravel 8.35+ / 9 / 10).
 */
class ApiTransport extends AbstractTransport
{
    public function __construct(
        private string $url,
        private string $apiKey,
        private ClientInterface $client,
        private int $timeout = 30,
        private int $connectTimeout = 10,
        private bool $verifySsl = true
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return 'shwanix';
    }

    protected function doSend(SentMessage $message): void
    {
        $email = $message->getOriginalMessage();
        if (! $email instanceof Email) {
            throw new TransportException(
                sprintf('Expected Symfony\Component\Mime\Email, got %s.', get_debug_type($email))
            );
        }

        $to = $this->addressesToStrings($email->getTo());
        $cc = $this->addressesToStrings($email->getCc());
        $bcc = $this->addressesToStrings($email->getBcc());

        $recipientCount = count(array_unique(array_merge($to, $cc, $bcc)));

        if ($this->url === '') {
            Log::error('Shwanix Mail API URL is not configured.', [
                'recipient_count' => $recipientCount,
            ]);

            throw new TransportException('Shwanix Mail API URL is not configured.');
        }

        if ($this->apiKey === '') {
            Log::error('Shwanix Mail API key is not configured.', [
                'recipient_count' => $recipientCount,
            ]);

            throw new TransportException('Shwanix Mail API key is not configured.');
        }

        $subject = (string) ($email->getSubject() ?? '');
        $htmlBody = $email->getHtmlBody();
        $textBody = $email->getTextBody();
        $body = $htmlBody !== null && $htmlBody !== ''
            ? $htmlBody
            : (string) ($textBody ?? '');

        $attachments = $this->collectAttachments($email);

        $payload = [
            'to' => $to,
            'cc' => $cc,
            'bcc' => $bcc,
            'subject' => $subject,
            'body' => $body,
            'attachments' => $attachments,
        ];

        try {
            $response = $this->client->request('POST', $this->url, [
                'headers' => [
                    'API-Key' => $this->apiKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => $payload,
                'http_errors' => false,
                'timeout' => $this->timeout,
                'connect_timeout' => $this->connectTimeout,
                'verify' => $this->verifySsl,
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode < 200 || $statusCode >= 300) {
                Log::error('Shwanix Mail API HTTP error', [
                    'status' => $statusCode,
                    'body' => $responseBody,
                    'recipient_count' => $recipientCount,
                ]);

                throw new TransportException(
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

                    throw new TransportException('Shwanix Mail API: '.$apiMessage);
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

            throw new TransportException('Shwanix Mail API: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * @param  iterable<Address>  $addresses
     * @return list<string>
     */
    private function addressesToStrings(iterable $addresses): array
    {
        $strings = [];
        foreach ($addresses as $address) {
            if ($address instanceof Address) {
                $strings[] = $address->getAddress();
            }
        }

        return array_values(array_unique($strings));
    }

    /**
     * @return list<array{filename: string, mime: string, content: string}>
     */
    private function collectAttachments(Email $email): array
    {
        $out = [];

        if (! method_exists($email, 'getAttachments')) {
            return $out;
        }

        foreach ($email->getAttachments() as $part) {
            if (! $part instanceof DataPart) {
                continue;
            }

            $filename = $part->getFilename() ?? 'attachment';
            $mediaType = $part->getMediaType();
            $mediaSubtype = $part->getMediaSubtype();
            $mime = $mediaType !== '' && $mediaSubtype !== ''
                ? $mediaType.'/'.$mediaSubtype
                : 'application/octet-stream';

            $out[] = [
                'filename' => $filename,
                'mime' => $mime,
                'content' => base64_encode($part->getBody()),
            ];
        }

        return $out;
    }
}
