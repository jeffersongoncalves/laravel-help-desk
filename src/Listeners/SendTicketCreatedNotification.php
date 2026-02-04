<?php

namespace JeffersonGoncalves\HelpDesk\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use JeffersonGoncalves\HelpDesk\Events\TicketCreated;
use JeffersonGoncalves\HelpDesk\Notifications\TicketCreatedNotification;

class SendTicketCreatedNotification implements ShouldQueue
{
    public function handle(TicketCreated $event): void
    {
        if (! config('help-desk.notifications.notify_on.ticket_created', true)) {
            return;
        }

        $ticket = $event->ticket;
        $user = $ticket->user;

        if ($user && method_exists($user, 'notify')) {
            $user->notify(new TicketCreatedNotification($ticket));
        }
    }
}
