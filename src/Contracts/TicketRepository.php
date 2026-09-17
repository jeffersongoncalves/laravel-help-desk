<?php

namespace JeffersonGoncalves\HelpDesk\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use JeffersonGoncalves\HelpDesk\Enums\TicketPriority;
use JeffersonGoncalves\HelpDesk\Enums\TicketStatus;
use JeffersonGoncalves\HelpDesk\Exceptions\InvalidStatusTransitionException;
use JeffersonGoncalves\HelpDesk\Exceptions\TicketNotFoundException;
use JeffersonGoncalves\HelpDesk\Models\Ticket;

/**
 * Where tickets live.
 *
 * "Repository" rather than "service contract" because an implementation is a
 * genuinely different source for the same data — the local database today, a
 * central application over a signed API next — not a different way of doing the
 * same work.
 *
 * The exceptions below are part of the contract: every implementation throws
 * them for the same conditions, so calling code reads the same on either.
 */
interface TicketRepository
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, Model $user): Ticket;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Ticket $ticket, array $data, ?Model $performer = null): Ticket;

    /**
     * @throws InvalidStatusTransitionException when the current status cannot reach the new one
     */
    public function changeStatus(Ticket $ticket, TicketStatus $newStatus, ?Model $performer = null): Ticket;

    public function assign(Ticket $ticket, Model $operator, ?Model $assignedBy = null): Ticket;

    public function unassign(Ticket $ticket, ?Model $performer = null): Ticket;

    /**
     * @throws InvalidStatusTransitionException
     */
    public function close(Ticket $ticket, ?Model $performer = null): Ticket;

    /**
     * @throws InvalidStatusTransitionException
     */
    public function reopen(Ticket $ticket, ?Model $performer = null): Ticket;

    public function delete(Ticket $ticket, ?Model $performer = null): bool;

    /**
     * @throws TicketNotFoundException when nothing matches, or the uuid is malformed
     */
    public function findByUuid(string $uuid): Ticket;

    /**
     * @throws TicketNotFoundException
     */
    public function findByReference(string $reference): Ticket;

    /**
     * The tickets a user opened, newest first — what an end-user list shows.
     *
     * Paginated because the API endpoint behind it always was: a satellite
     * asking for "all of them" got the first page and no way to tell.
     *
     * The actor is required rather than falling back to the authenticated
     * user. Which tickets someone may see is the one decision a caller must
     * not make by omission.
     *
     * Filtering, search and sort are on the contract rather than left to the
     * caller, because the API driver cannot do them in memory: narrowing the
     * page it happens to hold and presenting that as "your open tickets" looks
     * filtered and is wrong, with nothing on screen saying so. They narrow
     * what is asked for and never widen what the actor may see.
     *
     * `$search` covers the title and the reference number. `$sort` is one of
     * Ticket::SORTABLE; anything else throws on either driver rather than
     * being ignored.
     *
     * @param  array<int, TicketStatus|string>|TicketStatus|string|null  $status
     * @param  array<int, TicketPriority|string>|TicketPriority|string|null  $priority
     * @return LengthAwarePaginator<int, Ticket>
     *
     * @throws InvalidArgumentException for an unknown status, priority, sort column or direction
     */
    public function forActor(
        Model $user,
        int $perPage = 25,
        int $page = 1,
        array|TicketStatus|string|null $status = null,
        array|TicketPriority|string|null $priority = null,
        ?string $search = null,
        ?string $sort = null,
        string $direction = 'desc',
    ): LengthAwarePaginator;

    /**
     * Adding the same watcher twice is a no-op.
     */
    public function addWatcher(Ticket $ticket, Model $watcher): void;

    public function removeWatcher(Ticket $ticket, Model $watcher): void;
}
