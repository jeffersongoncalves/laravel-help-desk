<?php

namespace JeffersonGoncalves\HelpDesk\Mail\Drivers;

use JeffersonGoncalves\HelpDesk\Contracts\EmailDriver;
use JeffersonGoncalves\HelpDesk\Models\EmailChannel;

class PostmarkDriver implements EmailDriver
{
    public function poll(EmailChannel $channel): array
    {
        // Postmark uses webhooks, not polling
        return [];
    }

    public function getDriverName(): string
    {
        return 'postmark';
    }

    /**
     * Parse the inbound webhook payload from Postmark.
     *
     * Postmark sends the full email content (body, headers, attachments)
     * directly in the webhook POST payload as JSON.
     */
    public function parseWebhookPayload(array $payload): array
    {
        return [
            'message_id' => $payload['MessageID'] ?? null,
            'in_reply_to' => $this->extractHeader($payload, 'In-Reply-To'),
            'references' => $this->extractHeader($payload, 'References'),
            'from' => $this->extractFromAddress($payload),
            'from_name' => $payload['FromName'] ?? $this->extractFromName($payload),
            'to_addresses' => $this->extractRecipients($payload, 'ToFull', 'To'),
            'cc_addresses' => $this->extractRecipients($payload, 'CcFull', 'Cc'),
            'subject' => $payload['Subject'] ?? null,
            'text_body' => $payload['TextBody'] ?? null,
            'html_body' => $payload['HtmlBody'] ?? null,
            'stripped_reply' => $payload['StrippedTextReply'] ?? null,
            'mailbox_hash' => $payload['MailboxHash'] ?? null,
            'attachments' => $this->extractAttachments($payload),
            'raw_payload' => $payload,
        ];
    }

    protected function extractFromAddress(array $payload): ?string
    {
        if (isset($payload['FromFull']['Email'])) {
            return $payload['FromFull']['Email'];
        }

        if (isset($payload['From'])) {
            if (preg_match('/<([^>]+)>/', $payload['From'], $matches)) {
                return $matches[1];
            }

            if (filter_var(trim($payload['From']), FILTER_VALIDATE_EMAIL)) {
                return trim($payload['From']);
            }
        }

        return null;
    }

    protected function extractFromName(array $payload): ?string
    {
        if (isset($payload['FromFull']['Name'])) {
            return $payload['FromFull']['Name'];
        }

        return null;
    }

    /**
     * @return array<string>
     */
    protected function extractRecipients(array $payload, string $fullKey, string $fallbackKey): array
    {
        if (isset($payload[$fullKey]) && is_array($payload[$fullKey])) {
            return array_map(
                fn ($recipient) => $recipient['Email'] ?? $recipient['email'] ?? '',
                $payload[$fullKey]
            );
        }

        if (isset($payload[$fallbackKey]) && is_string($payload[$fallbackKey])) {
            return array_map('trim', explode(',', $payload[$fallbackKey]));
        }

        return [];
    }

    protected function extractHeader(array $payload, string $headerName): ?string
    {
        $headers = $payload['Headers'] ?? [];

        foreach ($headers as $header) {
            if (isset($header['Name']) && strcasecmp($header['Name'], $headerName) === 0) {
                return $header['Value'] ?? null;
            }
        }

        return null;
    }

    /**
     * Extract attachments from the Postmark payload.
     *
     * Postmark sends attachment content as base64-encoded data.
     */
    protected function extractAttachments(array $payload): array
    {
        $attachments = [];

        foreach ($payload['Attachments'] ?? [] as $attachment) {
            $attachments[] = [
                'filename' => $attachment['Name'] ?? null,
                'content' => $attachment['Content'] ?? null, // base64
                'content_type' => $attachment['ContentType'] ?? null,
                'content_length' => $attachment['ContentLength'] ?? null,
                'content_id' => $attachment['ContentID'] ?? null,
            ];
        }

        return $attachments;
    }
}
