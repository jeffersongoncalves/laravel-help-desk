<?php

namespace JeffersonGoncalves\HelpDesk\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use JeffersonGoncalves\HelpDesk\Enums\TicketStatus;
use JeffersonGoncalves\HelpDesk\Events\TicketStatusChanged;
use JeffersonGoncalves\HelpDesk\Notifications\TicketStatusChangedNotification;

class SendTicketStatusChangedNotification implements ShouldQueue
{
    public function handle(TicketStatusChanged $event): void
    {
        if (! config('help-desk.notifications.notify_on.ticket_status_changed', true)) {
            return;
        }

        // The closed notification says it better; sending both would mean two
        // emails for one action.
        if ($event->newStatus === TicketStatus::Closed && config('help-desk.notifications.notify_on.ticket_closed', true)) {
            return;
        }

        $event->ticket->notifyRequester(new TicketStatusChangedNotification(
            $event->ticket,
            $event->oldStatus,
            $event->newStatus,
        ));
    }
}
