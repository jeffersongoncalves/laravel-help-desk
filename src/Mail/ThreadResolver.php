<?php

namespace JeffersonGoncalves\HelpDesk\Mail;

use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Models\TicketComment;

class ThreadResolver
{
    public function resolve(array $parsedEmail): ?Ticket
    {
        // 1. Try matching via In-Reply-To header
        if (! empty($parsedEmail['in_reply_to'])) {
            $ticket = $this->findByEmailMessageId($parsedEmail['in_reply_to']);
            if ($ticket) {
                return $ticket;
            }
        }

        // 2. Try matching via References header
        if (! empty($parsedEmail['references'])) {
            $references = is_string($parsedEmail['references'])
                ? preg_split('/\s+/', $parsedEmail['references'])
                : $parsedEmail['references'];

            foreach (array_reverse($references) as $reference) {
                $ticket = $this->findByEmailMessageId(trim($reference));
                if ($ticket) {
                    return $ticket;
                }
            }
        }

        // 3. Try matching via subject line reference number
        if (! empty($parsedEmail['subject'])) {
            $ticket = $this->findBySubjectReference($parsedEmail['subject']);
            if ($ticket) {
                return $ticket;
            }
        }

        return null;
    }

    protected function findByEmailMessageId(string $messageId): ?Ticket
    {
        // Check tickets
        $ticket = Ticket::where('email_message_id', $messageId)->first();
        if ($ticket) {
            return $ticket;
        }

        // Check comments
        $comment = TicketComment::where('email_message_id', $messageId)->first();
        if ($comment) {
            return $comment->ticket;
        }

        return null;
    }

    protected function findBySubjectReference(string $subject): ?Ticket
    {
        $prefix = preg_quote(config('help-desk.ticket.reference_prefix', 'HD'), '/');

        if (preg_match('/'.$prefix.'-(\d+)/', $subject, $matches)) {
            $referenceNumber = $prefix.'-'.$matches[1];

            return Ticket::where('reference_number', $referenceNumber)->first();
        }

        return null;
    }
}
