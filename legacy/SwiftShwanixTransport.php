<?php

/**
 * SwiftMailer transport for Laravel 7 & 8 only. Loaded via require_once from ShwanixMailerServiceProvider
 * so this file is not parsed on Laravel 9+ (where Illuminate\Mail\Transport\Transport does not exist).
 */

namespace Danial\ShwanixMailer\Transport;

use Danial\ShwanixMailer\ShwanixApiClient;
use GuzzleHttp\ClientInterface;
use Illuminate\Mail\Transport\Transport;
use Illuminate\Support\Facades\Log;
use Swift_Attachment;
use Swift_Mime_Attachment;
use Swift_Mime_SimpleMessage;

class SwiftShwanixTransport extends Transport
{
    /** @var string */
    private $url;

    /** @var ClientInterface */
    private $client;

    /** @var int */
    private $timeout;

    /** @var int */
    private $connectTimeout;

    /** @var bool */
    private $verifySsl;

    public function __construct(
        string $url,
        ClientInterface $client,
        int $timeout = 30,
        int $connectTimeout = 10,
        bool $verifySsl = true
    ) {
        $this->url = $url;
        $this->client = $client;
        $this->timeout = $timeout;
        $this->connectTimeout = $connectTimeout;
        $this->verifySsl = $verifySsl;
    }

    /**
     * {@inheritdoc}
     */
    public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null)
    {
        $this->beforeSendPerformed($message);

        $to = $this->swiftAddressListToStrings($message->getTo());
        $cc = $this->swiftAddressListToStrings($message->getCc());
        $bcc = $this->swiftAddressListToStrings($message->getBcc());

        $recipientCount = count(array_unique(array_merge($to, $cc, $bcc)));

        if ($this->url === '') {
            Log::error('Shwanix Mail API URL is not configured.', [
                'recipient_count' => $recipientCount,
            ]);

            throw new \RuntimeException('Shwanix Mail API URL is not configured.');
        }

        $payload = [
            'to' => $to,
            'cc' => $cc,
            'bcc' => $bcc,
            'subject' => (string) $message->getSubject(),
            'body' => $this->extractBody($message),
            'attachments' => $this->collectAttachments($message),
        ];

        ShwanixApiClient::send(
            $this->client,
            $this->url,
            $payload,
            $recipientCount,
            $this->timeout,
            $this->connectTimeout,
            $this->verifySsl
        );

        $this->sendPerformed($message);

        return $this->numberOfRecipients($message);
    }

    /**
     * @param  array<string, string>|null  $addresses
     * @return list<string>
     */
    private function swiftAddressListToStrings($addresses): array
    {
        if (! is_array($addresses) || $addresses === []) {
            return [];
        }

        return array_values(array_unique(array_keys($addresses)));
    }

    private function extractBody(Swift_Mime_SimpleMessage $message): string
    {
        $ctype = (string) $message->getContentType();

        if (stripos($ctype, 'multipart') !== false) {
            $html = null;
            $plain = null;
            foreach ($message->getChildren() as $child) {
                $c = (string) $child->getContentType();
                if ($c === 'text/html') {
                    $html = (string) $child->getBody();
                } elseif ($c === 'text/plain') {
                    $plain = (string) $child->getBody();
                }
            }
            if ($html !== null && $html !== '') {
                return $html;
            }
            if ($plain !== null) {
                return $plain;
            }
        }

        return (string) $message->getBody();
    }

    /**
     * @return list<array{filename: string, mime: string, content: string}>
     */
    private function collectAttachments(Swift_Mime_SimpleMessage $message): array
    {
        $out = [];

        foreach ($message->getChildren() as $child) {
            if ($child instanceof Swift_Attachment || $child instanceof Swift_Mime_Attachment) {
                $filename = method_exists($child, 'getFilename') ? $child->getFilename() : null;
                $out[] = [
                    'filename' => $filename !== null && $filename !== '' ? $filename : 'attachment',
                    'mime' => $child->getContentType() ?: 'application/octet-stream',
                    'content' => base64_encode($child->getBody()),
                ];
            }
        }

        return $out;
    }
}
