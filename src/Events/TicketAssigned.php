<?php

namespace JeffersonGoncalves\HelpDesk\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JeffersonGoncalves\HelpDesk\Models\Ticket;

class TicketAssigned
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Ticket $ticket,
        public readonly object $assignedTo,
        public readonly ?object $assignedBy = null,
    ) {}
}
