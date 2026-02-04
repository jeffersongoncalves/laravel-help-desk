<?php

namespace JeffersonGoncalves\HelpDesk\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use JeffersonGoncalves\HelpDesk\Events\TicketAssigned;
use JeffersonGoncalves\HelpDesk\Notifications\TicketAssignedNotification;

class SendTicketAssignedNotification implements ShouldQueue
{
    public function handle(TicketAssigned $event): void
    {
        if (! config('help-desk.notifications.notify_on.ticket_assigned', true)) {
            return;
        }

        $assignedTo = $event->assignedTo;

        if (method_exists($assignedTo, 'notify')) {
            $assignedTo->notify(new TicketAssignedNotification($event->ticket));
        }
    }
}
