<?php

namespace JeffersonGoncalves\HelpDesk\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
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
     * @return LengthAwarePaginator<int, Ticket>
     */
    public function forActor(Model $user, int $perPage = 25, int $page = 1): LengthAwarePaginator;

    /**
     * Adding the same watcher twice is a no-op.
     */
    public function addWatcher(Ticket $ticket, Model $watcher): void;

    public function removeWatcher(Ticket $ticket, Model $watcher): void;
}
