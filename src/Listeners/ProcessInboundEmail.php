<?php

namespace JeffersonGoncalves\HelpDesk\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use JeffersonGoncalves\HelpDesk\Events\InboundEmailProcessed;
use JeffersonGoncalves\HelpDesk\Events\InboundEmailReceived;
use JeffersonGoncalves\HelpDesk\Exceptions\EmailProcessingException;
use JeffersonGoncalves\HelpDesk\Mail\EmailParser;
use JeffersonGoncalves\HelpDesk\Mail\ThreadResolver;
use JeffersonGoncalves\HelpDesk\Models\InboundEmail;
use JeffersonGoncalves\HelpDesk\Services\CommentService;
use JeffersonGoncalves\HelpDesk\Services\TicketService;

class ProcessInboundEmail implements ShouldQueue
{
    public function __construct(
        protected EmailParser $emailParser,
        protected ThreadResolver $threadResolver,
        protected TicketService $ticketService,
        protected CommentService $commentService,
    ) {}

    public function handle(InboundEmailReceived $event): void
    {
        $inboundEmail = $event->inboundEmail;

        if (! $inboundEmail->isPending()) {
            return;
        }

        try {
            $this->processEmail($inboundEmail);
        } catch (\Throwable $e) {
            $inboundEmail->markFailed($e->getMessage());
        }
    }

    protected function processEmail(InboundEmail $inboundEmail): void
    {
        $parsedEmail = $this->emailParser->parse($inboundEmail->toArray());

        if (empty($parsedEmail['from_address'])) {
            $inboundEmail->markFailed(__('help-desk::emails.inbound.no_sender'));

            return;
        }

        $user = $this->resolveUser($parsedEmail['from_address']);

        if (! $user) {
            $inboundEmail->markFailed(
                __('help-desk::emails.inbound.no_user', ['email' => $parsedEmail['from_address']])
            );

            return;
        }

        // Try to match to existing ticket
        $ticket = $this->threadResolver->resolve([
            'in_reply_to' => $inboundEmail->in_reply_to,
            'references' => $inboundEmail->references,
            'subject' => $inboundEmail->subject,
        ]);

        if ($ticket) {
            // Add comment to existing ticket
            $body = $inboundEmail->text_body ?? strip_tags($inboundEmail->html_body ?? '');

            if (empty(trim($body))) {
                $inboundEmail->markIgnored();

                return;
            }

            $comment = $this->commentService->addReply($ticket, $user, $body, [
                'email_message_id' => $inboundEmail->message_id,
            ]);

            $inboundEmail->markProcessed($ticket->id, $comment->id);
        } else {
            // Create new ticket
            $subject = $inboundEmail->subject ?: 'No Subject';
            $body = $inboundEmail->text_body ?? strip_tags($inboundEmail->html_body ?? '');

            if (empty(trim($body))) {
                $body = $subject;
            }

            $ticket = $this->ticketService->create([
                'title' => $subject,
                'description' => $body,
                'source' => 'email',
                'email_message_id' => $inboundEmail->message_id,
                'department_id' => $this->resolveDepartment($inboundEmail),
            ], $user);

            $inboundEmail->markProcessed($ticket->id);
        }

        event(new InboundEmailProcessed($inboundEmail));
    }

    protected function resolveUser(string $email): ?Model
    {
        $userModel = config('help-desk.models.user');

        return $userModel::where('email', $email)->first();
    }

    protected function resolveDepartment(InboundEmail $inboundEmail): int
    {
        // Try to match via email channel
        if ($inboundEmail->emailChannel && $inboundEmail->emailChannel->department_id) {
            return $inboundEmail->emailChannel->department_id;
        }

        // Try to match via recipient email
        $toAddresses = $inboundEmail->to_addresses ?? [];
        foreach ($toAddresses as $address) {
            $channel = \JeffersonGoncalves\HelpDesk\Models\EmailChannel::where('email_address', $address)->first();
            if ($channel && $channel->department_id) {
                return $channel->department_id;
            }
        }

        // Fall back to first active department
        $department = \JeffersonGoncalves\HelpDesk\Models\Department::where('is_active', true)
            ->orderBy('sort_order')
            ->first();

        if (! $department) {
            throw new EmailProcessingException('No active department found to assign the ticket.');
        }

        return $department->id;
    }
}
