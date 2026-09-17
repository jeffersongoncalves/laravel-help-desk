<?php

namespace JeffersonGoncalves\HelpDesk\Api\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use JeffersonGoncalves\HelpDesk\Api\HelpDeskClient;
use JeffersonGoncalves\HelpDesk\Api\HydratesModels;
use JeffersonGoncalves\HelpDesk\Api\ResolvesActor;
use JeffersonGoncalves\HelpDesk\Contracts\TicketRepository;
use JeffersonGoncalves\HelpDesk\Enums\TicketStatus;
use JeffersonGoncalves\HelpDesk\Exceptions\HelpDeskApiException;
use JeffersonGoncalves\HelpDesk\Exceptions\TicketNotFoundException;
use JeffersonGoncalves\HelpDesk\Models\Ticket;

/**
 * Tickets, over the signed API.
 *
 * The operator half of the contract throws immediately rather than issuing a
 * request that would 404. A satellite finding out it cannot close a ticket
 * should read why, not read a status code.
 */
class ApiTicketRepository implements TicketRepository
{
    use HydratesModels, ResolvesActor;

    public function __construct(protected HelpDeskClient $client) {}

    public function create(array $data, Model $user): Ticket
    {
        $payload = $this->client->post('tickets', $data + ['actor' => $this->actorPayload($user)]);

        return $this->hydrateTicket($payload['data']);
    }

    public function findByUuid(string $uuid): Ticket
    {
        $payload = $this->client->get("tickets/{$uuid}", ['actor' => $this->actorPayload()]);

        return $this->hydrateTicket($payload['data']);
    }

    /**
     * The central application keys tickets by uuid, so a reference lookup is a
     * filtered list rather than an endpoint of its own.
     */
    public function findByReference(string $reference): Ticket
    {
        $payload = $this->client->get('tickets', [
            'actor' => $this->actorPayload(),
            'reference_number' => $reference,
        ]);

        $first = $payload['data'][0] ?? null;

        if (! is_array($first)) {
            throw TicketNotFoundException::withReference($reference);
        }

        return $this->hydrateTicket($first);
    }

    /**
     * The endpoint has always paginated. Returning only `data` meant a
     * satellite with more than a page of tickets was shown the first page and
     * told nothing, so the totals the response already carried are kept.
     *
     * @return LengthAwarePaginator<int, Ticket>
     */
    public function forActor(Model $user, int $perPage = 25, int $page = 1): LengthAwarePaginator
    {
        $payload = $this->client->get('tickets', [
            'actor' => $this->actorPayload($user),
            'per_page' => $perPage,
            'page' => $page,
        ]);

        $meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];
        $items = collect($payload['data'] ?? [])
            ->values()
            ->map(fn (array $ticket): Ticket => $this->hydrateTicket($ticket));

        // Falling back to the items themselves, rather than to zero: a central
        // application that answers without meta should look like one page of
        // what it sent, not like an empty list.
        return new Paginator(
            items: $items,
            total: (int) ($meta['total'] ?? $items->count()),
            perPage: (int) ($meta['per_page'] ?? $perPage),
            currentPage: (int) ($meta['current_page'] ?? $page),
        );
    }

    public function update(Ticket $ticket, array $data, ?Model $performer = null): Ticket
    {
        throw HelpDeskApiException::operatorOnly('updateTicket()');
    }

    /**
     * Two of the six statuses are the requester's own. The rest carry operator
     * and SLA meaning and have no endpoint, so they are refused here rather
     * than sent and rejected.
     *
     * This is the honest line: not "status changes are operator-only", but
     * "these two are yours, the others are ours".
     */
    public function changeStatus(Ticket $ticket, TicketStatus $newStatus, ?Model $performer = null): Ticket
    {
        if (! in_array($newStatus, [TicketStatus::Closed, TicketStatus::Open], true)) {
            throw HelpDeskApiException::operatorOnly('Changing a ticket to '.$newStatus->value);
        }

        $payload = $this->client->post("tickets/{$ticket->uuid}/status", [
            'actor' => $this->actorPayload($performer),
            'status' => $newStatus->value,
        ]);

        return $this->hydrateTicket($payload['data']);
    }

    public function assign(Ticket $ticket, Model $operator, ?Model $assignedBy = null): Ticket
    {
        throw HelpDeskApiException::operatorOnly('assignTicket()');
    }

    public function unassign(Ticket $ticket, ?Model $performer = null): Ticket
    {
        throw HelpDeskApiException::operatorOnly('unassignTicket()');
    }

    public function close(Ticket $ticket, ?Model $performer = null): Ticket
    {
        return $this->changeStatus($ticket, TicketStatus::Closed, $performer);
    }

    public function reopen(Ticket $ticket, ?Model $performer = null): Ticket
    {
        return $this->changeStatus($ticket, TicketStatus::Open, $performer);
    }

    public function delete(Ticket $ticket, ?Model $performer = null): bool
    {
        throw HelpDeskApiException::operatorOnly('deleteTicket()');
    }

    public function addWatcher(Ticket $ticket, Model $watcher): void
    {
        throw HelpDeskApiException::operatorOnly('addWatcher()');
    }

    public function removeWatcher(Ticket $ticket, Model $watcher): void
    {
        throw HelpDeskApiException::operatorOnly('removeWatcher()');
    }
}
