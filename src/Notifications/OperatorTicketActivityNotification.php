<?php

namespace JeffersonGoncalves\HelpDesk\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Models\TicketComment;

/**
 * Tells an operator that the requester opened a ticket, or replied to one when
 * a comment is given.
 */
class OperatorTicketActivityNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Ticket $ticket,
        public readonly ?TicketComment $comment = null,
    ) {
        $this->queue = config('help-desk.notifications.queue', 'default');
    }

    public function via(object $notifiable): array
    {
        return config('help-desk.notifications.channels', ['mail']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $key = $this->comment === null ? 'operator_new_ticket' : 'operator_requester_reply';

        $message = (new MailMessage)
            ->subject(__("help-desk::notifications.{$key}.subject", [
                'reference' => $this->ticket->reference_number,
                'title' => $this->ticket->title,
            ]))
            ->greeting(__("help-desk::notifications.{$key}.greeting"))
            ->line(__("help-desk::notifications.{$key}.body", [
                'requester' => $this->ticket->requester_name ?? $this->ticket->requester_email ?? '-',
            ]))
            ->line(__("help-desk::notifications.{$key}.title", ['title' => $this->ticket->title]))
            ->line(__("help-desk::notifications.{$key}.priority", [
                'priority' => $this->ticket->priority->label(),
            ]))
            ->action(__("help-desk::notifications.{$key}.action"), url('/'));

        if (config('help-desk.email.threading_enabled', true)) {
            $this->addThreadingHeaders($message);
        }

        return $message;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'ticket_uuid' => $this->ticket->uuid,
            'reference_number' => $this->ticket->reference_number,
            'title' => $this->ticket->title,
            'comment_id' => $this->comment?->id,
        ];
    }

    protected function addThreadingHeaders(MailMessage $message): void
    {
        $domain = parse_url(config('app.url', 'localhost'), PHP_URL_HOST) ?: 'localhost';
        $suffix = $this->comment === null ? 'operator' : 'operator-comment-'.$this->comment->id;
        $messageId = sprintf('%s-%s@%s', $this->ticket->uuid, $suffix, $domain);
        $references = sprintf('<%s-%s@%s>', $this->ticket->uuid, 'created', $domain);

        $message->withSymfonyMessage(function ($symfonyMessage) use ($messageId, $references) {
            $symfonyMessage->getHeaders()->addIdHeader('Message-ID', $messageId);
            $symfonyMessage->getHeaders()->addTextHeader('In-Reply-To', $references);
            $symfonyMessage->getHeaders()->addTextHeader('References', $references);
            $symfonyMessage->getHeaders()->addTextHeader('X-HelpDesk-Ticket-Ref', $this->ticket->reference_number);
        });
    }
}
