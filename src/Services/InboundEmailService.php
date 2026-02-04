<?php

namespace JeffersonGoncalves\HelpDesk\Services;

use JeffersonGoncalves\HelpDesk\Events\InboundEmailReceived;
use JeffersonGoncalves\HelpDesk\Models\InboundEmail;

class InboundEmailService
{
    public function store(array $data): InboundEmail
    {
        $existing = InboundEmail::where('message_id', $data['message_id'])->first();

        if ($existing) {
            return $existing;
        }

        $inboundEmail = InboundEmail::create($data);

        event(new InboundEmailReceived($inboundEmail));

        return $inboundEmail;
    }

    public function markProcessed(InboundEmail $inboundEmail, ?int $ticketId = null, ?int $commentId = null): void
    {
        $inboundEmail->markProcessed($ticketId, $commentId);
    }

    public function markFailed(InboundEmail $inboundEmail, string $errorMessage): void
    {
        $inboundEmail->markFailed($errorMessage);
    }

    public function markIgnored(InboundEmail $inboundEmail): void
    {
        $inboundEmail->markIgnored();
    }

    public function cleanOldEmails(?int $days = null): int
    {
        $days = $days ?? config('help-desk.email.inbound.retention_days', 30);

        return InboundEmail::where('created_at', '<', now()->subDays($days))
            ->whereIn('status', ['processed', 'ignored'])
            ->delete();
    }
}
