<?php

namespace JeffersonGoncalves\HelpDesk\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use JeffersonGoncalves\HelpDesk\Models\Ticket;

class TicketAutomationTriggeredNotification extends Notification implements ShouldQueue
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
        return (new MailMessage)
            ->subject(__('help-desk::notifications.automation_triggered.subject', [
                'reference' => $this->ticket->reference_number,
            ]))
            ->greeting(__('help-desk::notifications.automation_triggered.greeting'))
            ->line(__('help-desk::notifications.automation_triggered.body'))
            ->line(__('help-desk::notifications.automation_triggered.title', [
                'title' => $this->ticket->title,
            ]))
            ->action(__('help-desk::notifications.automation_triggered.action'), url('/'));
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
}
