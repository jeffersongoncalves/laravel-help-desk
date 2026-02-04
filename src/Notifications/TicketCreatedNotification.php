<?php

namespace JeffersonGoncalves\HelpDesk\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use JeffersonGoncalves\HelpDesk\Models\Ticket;

class TicketCreatedNotification extends Notification implements ShouldQueue
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
            ->subject(__('help-desk::notifications.ticket_created.subject', [
                'title' => $this->ticket->title,
            ]))
            ->greeting(__('help-desk::notifications.ticket_created.greeting'))
            ->line(__('help-desk::notifications.ticket_created.body'))
            ->line(__('help-desk::notifications.ticket_created.reference', [
                'reference' => $this->ticket->reference_number,
            ]))
            ->line(__('help-desk::notifications.ticket_created.department', [
                'department' => $this->ticket->department->name,
            ]))
            ->line(__('help-desk::notifications.ticket_created.priority', [
                'priority' => $this->ticket->priority->label(),
            ]))
            ->action(__('help-desk::notifications.ticket_created.action'), url('/'));

        if (config('help-desk.email.threading_enabled', true)) {
            $messageId = $this->generateMessageId();
            $message->withSymfonyMessage(function ($symfonyMessage) use ($messageId) {
                $symfonyMessage->getHeaders()->addTextHeader('Message-ID', $messageId);
                $symfonyMessage->getHeaders()->addTextHeader('X-HelpDesk-Ticket-Ref', $this->ticket->reference_number);
            });
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
            'status' => $this->ticket->status->value,
            'priority' => $this->ticket->priority->value,
        ];
    }

    protected function generateMessageId(): string
    {
        $domain = parse_url(config('app.url', 'localhost'), PHP_URL_HOST) ?: 'localhost';

        return sprintf('<%s-%s@%s>', $this->ticket->uuid, 'created', $domain);
    }
}
