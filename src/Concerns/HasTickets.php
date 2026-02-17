<?php

namespace JeffersonGoncalves\HelpDesk\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Models\TicketComment;
use JeffersonGoncalves\HelpDesk\Models\TicketWatcher;

/**
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Ticket> $helpDeskTickets
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TicketComment> $helpDeskComments
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TicketWatcher> $helpDeskWatching
 */
trait HasTickets
{
    /** @return MorphMany<Ticket, $this> */
    public function helpDeskTickets(): MorphMany
    {
        return $this->morphMany(Ticket::class, 'user');
    }

    /** @return MorphMany<TicketComment, $this> */
    public function helpDeskComments(): MorphMany
    {
        return $this->morphMany(TicketComment::class, 'author');
    }

    /** @return MorphMany<TicketWatcher, $this> */
    public function helpDeskWatching(): MorphMany
    {
        return $this->morphMany(TicketWatcher::class, 'watcher');
    }
}
