<?php

namespace JeffersonGoncalves\HelpDesk\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use JeffersonGoncalves\HelpDesk\Events\TicketClosed;
use JeffersonGoncalves\HelpDesk\Notifications\TicketClosedNotification;

class SendTicketClosedNotification implements ShouldQueue
{
    public function handle(TicketClosed $event): void
    {
        if (! config('help-desk.notifications.notify_on.ticket_closed', true)) {
            return;
        }

        $event->ticket->notifyRequester(new TicketClosedNotification($event->ticket));
    }
}
