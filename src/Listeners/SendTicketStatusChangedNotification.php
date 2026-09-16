<?php

namespace JeffersonGoncalves\HelpDesk\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use JeffersonGoncalves\HelpDesk\Events\TicketStatusChanged;
use JeffersonGoncalves\HelpDesk\Notifications\TicketStatusChangedNotification;

class SendTicketStatusChangedNotification implements ShouldQueue
{
    public function handle(TicketStatusChanged $event): void
    {
        if (! config('help-desk.notifications.notify_on.ticket_status_changed', true)) {
            return;
        }

        $event->ticket->notifyRequester(new TicketStatusChangedNotification(
            $event->ticket,
            $event->oldStatus,
            $event->newStatus,
        ));
    }
}
