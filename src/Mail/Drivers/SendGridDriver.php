<?php

namespace JeffersonGoncalves\HelpDesk\Mail\Drivers;

use JeffersonGoncalves\HelpDesk\Contracts\EmailDriver;
use JeffersonGoncalves\HelpDesk\Models\EmailChannel;

class SendGridDriver implements EmailDriver
{
    public function poll(EmailChannel $channel): array
    {
        // SendGrid uses webhooks, not polling
        return [];
    }

    public function getDriverName(): string
    {
        return 'sendgrid';
    }

    public function parseWebhookPayload(array $payload): array
    {
        $envelope = json_decode($payload['envelope'] ?? '{}', true);

        return [
            'message_id' => $this->extractHeader($payload, 'Message-ID'),
            'in_reply_to' => $this->extractHeader($payload, 'In-Reply-To'),
            'references' => $this->extractHeader($payload, 'References'),
            'from' => $payload['from'] ?? null,
            'to_addresses' => $envelope['to'] ?? (isset($payload['to']) ? [$payload['to']] : []),
            'cc_addresses' => isset($payload['cc']) ? [$payload['cc']] : [],
            'subject' => $payload['subject'] ?? null,
            'text_body' => $payload['text'] ?? null,
            'html_body' => $payload['html'] ?? null,
            'raw_payload' => $payload,
        ];
    }

    protected function extractHeader(array $payload, string $header): ?string
    {
        $headers = $payload['headers'] ?? '';

        if (is_string($headers) && preg_match('/'.preg_quote($header, '/').': (.+)/i', $headers, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }
}
