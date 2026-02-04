<?php

namespace JeffersonGoncalves\HelpDesk\Mail\Drivers;

use JeffersonGoncalves\HelpDesk\Contracts\EmailDriver;
use JeffersonGoncalves\HelpDesk\Models\EmailChannel;

class MailgunDriver implements EmailDriver
{
    public function poll(EmailChannel $channel): array
    {
        // Mailgun uses webhooks, not polling
        return [];
    }

    public function getDriverName(): string
    {
        return 'mailgun';
    }

    public function parseWebhookPayload(array $payload): array
    {
        return [
            'message_id' => $payload['Message-Id'] ?? $payload['message-id'] ?? null,
            'in_reply_to' => $payload['In-Reply-To'] ?? null,
            'references' => $payload['References'] ?? null,
            'from' => $payload['from'] ?? $payload['sender'] ?? null,
            'to_addresses' => isset($payload['recipient']) ? [$payload['recipient']] : [],
            'cc_addresses' => [],
            'subject' => $payload['subject'] ?? $payload['Subject'] ?? null,
            'text_body' => $payload['body-plain'] ?? null,
            'html_body' => $payload['body-html'] ?? null,
            'raw_payload' => $payload,
        ];
    }
}
