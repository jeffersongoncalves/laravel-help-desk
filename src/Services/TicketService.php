<?php

namespace JeffersonGoncalves\HelpDesk\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use JeffersonGoncalves\HelpDesk\Enums\TicketPriority;
use JeffersonGoncalves\HelpDesk\Enums\TicketStatus;
use JeffersonGoncalves\HelpDesk\Events\TicketAssigned;
use JeffersonGoncalves\HelpDesk\Events\TicketClosed;
use JeffersonGoncalves\HelpDesk\Events\TicketCreated;
use JeffersonGoncalves\HelpDesk\Events\TicketDeleted;
use JeffersonGoncalves\HelpDesk\Events\TicketPriorityChanged;
use JeffersonGoncalves\HelpDesk\Events\TicketReopened;
use JeffersonGoncalves\HelpDesk\Events\TicketStatusChanged;
use JeffersonGoncalves\HelpDesk\Events\TicketUpdated;
use JeffersonGoncalves\HelpDesk\Exceptions\InvalidStatusTransitionException;
use JeffersonGoncalves\HelpDesk\Exceptions\TicketNotFoundException;
use JeffersonGoncalves\HelpDesk\Models\Ticket;

class TicketService
{
    public function create(array $data, Model $user): Ticket
    {
        return DB::transaction(function () use ($data, $user) {
            $ticket = new Ticket();
            $ticket->fill($data);
            $ticket->user_type = $user->getMorphClass();
            $ticket->user_id = $user->getKey();

            if (! isset($data['source'])) {
                $ticket->source = 'web';
            }

            $ticket->save();
            $ticket->load(['department', 'category']);

            event(new TicketCreated($ticket));

            return $ticket;
        });
    }

    public function update(Ticket $ticket, array $data, ?Model $performer = null): Ticket
    {
        return DB::transaction(function () use ($ticket, $data, $performer) {
            $oldStatus = $ticket->status;
            $oldPriority = $ticket->priority;

            $ticket->fill($data);
            $changes = $ticket->getDirty();
            $ticket->save();

            if (isset($changes['status']) && $oldStatus !== $ticket->status) {
                event(new TicketStatusChanged($ticket, $oldStatus, $ticket->status, $performer));

                if ($ticket->status === TicketStatus::Closed) {
                    $ticket->update(['closed_at' => now()]);
                    event(new TicketClosed($ticket, $performer));
                }

                if ($oldStatus === TicketStatus::Closed && $ticket->status === TicketStatus::Open) {
                    $ticket->update(['closed_at' => null]);
                    event(new TicketReopened($ticket, $performer));
                }
            }

            if (isset($changes['priority']) && $oldPriority !== $ticket->priority) {
                event(new TicketPriorityChanged($ticket, $oldPriority, $ticket->priority, $performer));
            }

            event(new TicketUpdated($ticket, $changes));

            return $ticket->fresh();
        });
    }

    public function changeStatus(Ticket $ticket, TicketStatus $newStatus, ?Model $performer = null): Ticket
    {
        $oldStatus = $ticket->status;

        if (! $oldStatus->canTransitionTo($newStatus)) {
            throw InvalidStatusTransitionException::make($oldStatus, $newStatus);
        }

        return $this->update($ticket, ['status' => $newStatus], $performer);
    }

    public function assign(Ticket $ticket, Model $operator, ?Model $assignedBy = null): Ticket
    {
        $ticket->assigned_to_type = $operator->getMorphClass();
        $ticket->assigned_to_id = $operator->getKey();
        $ticket->save();

        event(new TicketAssigned($ticket, $operator, $assignedBy));

        return $ticket;
    }

    public function unassign(Ticket $ticket, ?Model $performer = null): Ticket
    {
        $ticket->assigned_to_type = null;
        $ticket->assigned_to_id = null;
        $ticket->save();

        event(new TicketUpdated($ticket, ['assigned_to_id' => null]));

        return $ticket;
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
        event(new TicketDeleted($ticket, $performer));

        return $ticket->delete();
    }

    public function findByUuid(string $uuid): Ticket
    {
        $ticket = Ticket::where('uuid', $uuid)->first();

        if (! $ticket) {
            throw TicketNotFoundException::withUuid($uuid);
        }

        return $ticket;
    }

    public function findByReference(string $reference): Ticket
    {
        $ticket = Ticket::where('reference_number', $reference)->first();

        if (! $ticket) {
            throw TicketNotFoundException::withReference($reference);
        }

        return $ticket;
    }

    public function addWatcher(Ticket $ticket, Model $watcher): void
    {
        $ticket->watchers()->firstOrCreate([
            'watcher_type' => $watcher->getMorphClass(),
            'watcher_id' => $watcher->getKey(),
        ]);
    }

    public function removeWatcher(Ticket $ticket, Model $watcher): void
    {
        $ticket->watchers()
            ->where('watcher_type', $watcher->getMorphClass())
            ->where('watcher_id', $watcher->getKey())
            ->delete();
    }
}
