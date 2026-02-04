<?php

namespace JeffersonGoncalves\HelpDesk\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use JeffersonGoncalves\HelpDesk\Models\Ticket;

class TicketClosedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Ticket $ticket,
    ) {
        $this->queue = config('help-desk.notifications.queue', 'default');
    }

    public function via(object $notifiable): array
    {
        return config('help-desk.notifications.channels', ['mail']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(__('help-desk::notifications.ticket_closed.subject', [
                'reference' => $this->ticket->reference_number,
            ]))
            ->greeting(__('help-desk::notifications.ticket_closed.greeting'))
            ->line(__('help-desk::notifications.ticket_closed.body'))
            ->line(__('help-desk::notifications.ticket_closed.title', [
                'title' => $this->ticket->title,
            ]))
            ->action(__('help-desk::notifications.ticket_closed.action'), url('/'));

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
        ];
    }

    protected function addThreadingHeaders(MailMessage $message): void
    {
        $domain = parse_url(config('app.url', 'localhost'), PHP_URL_HOST) ?: 'localhost';
        $messageId = sprintf('<%s-%s-%s@%s>', $this->ticket->uuid, 'closed', time(), $domain);
        $references = sprintf('<%s-%s@%s>', $this->ticket->uuid, 'created', $domain);

        $message->withSymfonyMessage(function ($symfonyMessage) use ($messageId, $references) {
            $symfonyMessage->getHeaders()->addTextHeader('Message-ID', $messageId);
            $symfonyMessage->getHeaders()->addTextHeader('In-Reply-To', $references);
            $symfonyMessage->getHeaders()->addTextHeader('References', $references);
            $symfonyMessage->getHeaders()->addTextHeader('X-HelpDesk-Ticket-Ref', $this->ticket->reference_number);
        });
    }
}
