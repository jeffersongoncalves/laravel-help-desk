<?php

namespace JeffersonGoncalves\HelpDesk\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Models\TicketComment;
use JeffersonGoncalves\HelpDesk\Models\TicketWatcher;

trait HasTickets
{
    public function helpDeskTickets(): MorphMany
    {
        return $this->morphMany(Ticket::class, 'user');
    }

    public function helpDeskComments(): MorphMany
    {
        return $this->morphMany(TicketComment::class, 'author');
    }

    public function helpDeskWatching(): MorphMany
    {
        return $this->morphMany(TicketWatcher::class, 'watcher');
    }
}
