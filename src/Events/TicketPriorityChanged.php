<?php

namespace JeffersonGoncalves\HelpDesk\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JeffersonGoncalves\HelpDesk\Enums\TicketPriority;
use JeffersonGoncalves\HelpDesk\Models\Ticket;

class TicketPriorityChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Ticket $ticket,
        public readonly TicketPriority $oldPriority,
        public readonly TicketPriority $newPriority,
        public readonly ?object $performer = null,
    ) {}
}
