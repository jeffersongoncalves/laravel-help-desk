<?php

namespace JeffersonGoncalves\HelpDesk\Mail\Drivers;

use Illuminate\Support\Facades\Http;
use JeffersonGoncalves\HelpDesk\Contracts\EmailDriver;
use JeffersonGoncalves\HelpDesk\Exceptions\EmailProcessingException;
use JeffersonGoncalves\HelpDesk\Models\EmailChannel;

class ResendDriver implements EmailDriver
{
    public function poll(EmailChannel $channel): array
    {
        // Resend uses webhooks, not polling
        return [];
    }

    public function getDriverName(): string
    {
        return 'resend';
    }

    /**
     * Parse the webhook payload from Resend.
     *
     * Note: Resend webhooks do NOT include the email body or attachment content.
     * Only metadata is sent. The body must be fetched via the Resend API.
     */
    public function parseWebhookPayload(array $payload): array
    {
        $data = $payload['data'] ?? $payload;

        return [
            'message_id' => $data['message_id'] ?? null,
            'email_id' => $data['email_id'] ?? null,
            'in_reply_to' => null, // Not available in webhook, must be fetched via API
            'references' => null,
            'from' => $data['from'] ?? null,
            'to_addresses' => $data['to'] ?? [],
            'cc_addresses' => $data['cc'] ?? [],
            'subject' => $data['subject'] ?? null,
            'text_body' => null, // Must be fetched via Resend API
            'html_body' => null, // Must be fetched via Resend API
            'attachments_metadata' => $data['attachments'] ?? [],
            'raw_payload' => $payload,
        ];
    }

    /**
     * Fetch the full email content from the Resend API.
     *
     * Resend webhooks only contain metadata. This method fetches
     * the full email body and headers from the Resend Received Emails API.
     */
    public function fetchEmailContent(string $emailId, string $apiKey): array
    {
        $response = Http::withToken($apiKey)
            ->get("https://api.resend.com/emails/{$emailId}");

        if (! $response->successful()) {
            throw EmailProcessingException::parsingFailed(
                "Failed to fetch email content from Resend API: {$response->status()}"
            );
        }

        $data = $response->json();

        return [
            'text_body' => $data['text'] ?? null,
            'html_body' => $data['html'] ?? null,
            'headers' => $data['headers'] ?? [],
            'in_reply_to' => $this->extractHeader($data['headers'] ?? [], 'In-Reply-To'),
            'references' => $this->extractHeader($data['headers'] ?? [], 'References'),
        ];
    }

    /**
     * Fetch attachment content from the Resend API.
     */
    public function fetchAttachments(string $emailId, string $apiKey): array
    {
        $response = Http::withToken($apiKey)
            ->get("https://api.resend.com/emails/{$emailId}/attachments");

        if (! $response->successful()) {
            return [];
        }

        return $response->json('data') ?? [];
    }

    /**
     * Verify the webhook signature using Svix headers.
     */
    public function verifyWebhookSignature(string $payload, array $headers, string $secret): bool
    {
        $svixId = $headers['svix-id'] ?? null;
        $svixTimestamp = $headers['svix-timestamp'] ?? null;
        $svixSignature = $headers['svix-signature'] ?? null;

        if (! $svixId || ! $svixTimestamp || ! $svixSignature) {
            return false;
        }

        $signedContent = "{$svixId}.{$svixTimestamp}.{$payload}";

        // The secret may be prefixed with "whsec_"
        $secretBytes = base64_decode(
            str_starts_with($secret, 'whsec_') ? substr($secret, 6) : $secret
        );

        $expectedSignature = base64_encode(
            hash_hmac('sha256', $signedContent, $secretBytes, true)
        );

        // Svix may send multiple signatures separated by spaces
        $signatures = explode(' ', $svixSignature);

        foreach ($signatures as $sig) {
            $parts = explode(',', $sig, 2);
            $sigValue = $parts[1] ?? $parts[0];

            if (hash_equals($expectedSignature, $sigValue)) {
                return true;
            }
        }

        return false;
    }

    protected function extractHeader(array $headers, string $name): ?string
    {
        foreach ($headers as $header) {
            if (is_array($header) && isset($header['name']) && strcasecmp($header['name'], $name) === 0) {
                return $header['value'] ?? null;
            }
        }

        return null;
    }
}
