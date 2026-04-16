<?php

namespace Danial\ShwanixMailer\Transport;

use Danial\ShwanixMailer\RecipientFormat;
use Danial\ShwanixMailer\ShwanixApiClient;
use GuzzleHttp\ClientInterface;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

/**
 * Sends mail by POSTing JSON to the Shwanix HTTP API (Laravel 9+ / Symfony Mailer).
 */
class ApiTransport extends AbstractTransport
{
    public function __construct(
        private string $url,
        private ClientInterface $client,
        private int $timeout = 30,
        private int $connectTimeout = 10,
        private bool $verifySsl = true,
        private string $apiKey = ''
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

        $subject = (string) ($email->getSubject() ?? '');
        $htmlBody = $email->getHtmlBody();
        $textBody = $email->getTextBody();
        $body = $htmlBody !== null && $htmlBody !== ''
            ? $htmlBody
            : (string) ($textBody ?? '');

        $attachments = $this->collectAttachments($email);

        $payload = [
            'to' => RecipientFormat::toApiField($to),
            'cc' => RecipientFormat::toApiField($cc),
            'bcc' => RecipientFormat::toApiField($bcc),
            'subject' => $subject,
            'body' => $body,
            'attachments' => $attachments,
        ];

        try {
            ShwanixApiClient::send(
                $this->client,
                $this->url,
                $payload,
                $recipientCount,
                $this->timeout,
                $this->connectTimeout,
                $this->verifySsl,
                $this->apiKey
            );
        } catch (\RuntimeException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            Log::error('Shwanix Mail API unexpected error', [
                'exception' => $e->getMessage(),
                'recipient_count' => $recipientCount,
            ]);

            throw new TransportException('Shwanix Mail API: '.$e->getMessage(), (int) $e->getCode(), $e);
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
