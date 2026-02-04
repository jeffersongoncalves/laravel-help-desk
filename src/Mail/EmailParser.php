<?php

namespace JeffersonGoncalves\HelpDesk\Mail;

class EmailParser
{
    public function parse(array $rawData): array
    {
        return [
            'message_id' => $this->extractMessageId($rawData),
            'in_reply_to' => $rawData['in_reply_to'] ?? $rawData['In-Reply-To'] ?? null,
            'references' => $rawData['references'] ?? $rawData['References'] ?? null,
            'from_address' => $this->extractFromAddress($rawData),
            'from_name' => $this->extractFromName($rawData),
            'to_addresses' => $this->extractAddresses($rawData, 'to'),
            'cc_addresses' => $this->extractAddresses($rawData, 'cc'),
            'subject' => $rawData['subject'] ?? $rawData['Subject'] ?? null,
            'text_body' => $this->extractTextBody($rawData),
            'html_body' => $rawData['html_body'] ?? $rawData['body-html'] ?? $rawData['html'] ?? null,
            'attachments' => $rawData['attachments'] ?? [],
        ];
    }

    protected function extractMessageId(array $data): string
    {
        return $data['message_id']
            ?? $data['Message-Id']
            ?? $data['Message-ID']
            ?? '<'.uniqid('helpdesk-', true).'@generated>';
    }

    protected function extractFromAddress(array $data): ?string
    {
        if (isset($data['from_address'])) {
            return $data['from_address'];
        }

        if (isset($data['from']) && is_string($data['from'])) {
            return $this->parseEmailFromString($data['from']);
        }

        if (isset($data['From'])) {
            return $this->parseEmailFromString($data['From']);
        }

        if (isset($data['sender'])) {
            return $this->parseEmailFromString($data['sender']);
        }

        return null;
    }

    protected function extractFromName(array $data): ?string
    {
        if (isset($data['from_name'])) {
            return $data['from_name'];
        }

        $from = $data['from'] ?? $data['From'] ?? null;

        if ($from && is_string($from)) {
            return $this->parseNameFromString($from);
        }

        return null;
    }

    protected function extractAddresses(array $data, string $field): array
    {
        $key = $field.'_addresses';
        if (isset($data[$key]) && is_array($data[$key])) {
            return $data[$key];
        }

        $value = $data[$field] ?? $data[ucfirst($field)] ?? null;

        if (is_string($value)) {
            return array_map('trim', explode(',', $value));
        }

        if (is_array($value)) {
            return $value;
        }

        return [];
    }

    protected function extractTextBody(array $data): ?string
    {
        $body = $data['text_body']
            ?? $data['body-plain']
            ?? $data['text']
            ?? $data['body']
            ?? null;

        if ($body) {
            return $this->cleanTextBody($body);
        }

        return null;
    }

    public function cleanTextBody(string $body): string
    {
        // Remove common email signatures and quoted text
        $lines = explode("\n", $body);
        $cleanLines = [];

        foreach ($lines as $line) {
            // Stop at common reply indicators
            if (preg_match('/^(>|On .+ wrote:|---+\s*Original Message)/i', trim($line))) {
                break;
            }
            $cleanLines[] = $line;
        }

        return trim(implode("\n", $cleanLines));
    }

    protected function parseEmailFromString(string $value): ?string
    {
        if (preg_match('/<([^>]+)>/', $value, $matches)) {
            return $matches[1];
        }

        if (filter_var(trim($value), FILTER_VALIDATE_EMAIL)) {
            return trim($value);
        }

        return null;
    }

    protected function parseNameFromString(string $value): ?string
    {
        if (preg_match('/^(.+?)\s*</', $value, $matches)) {
            return trim($matches[1], ' "\'');
        }

        return null;
    }
}
