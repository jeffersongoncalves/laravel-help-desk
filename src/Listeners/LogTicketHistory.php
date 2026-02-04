<?php

namespace JeffersonGoncalves\HelpDesk\Listeners;

use JeffersonGoncalves\HelpDesk\Enums\HistoryAction;
use JeffersonGoncalves\HelpDesk\Events\AttachmentAdded;
use JeffersonGoncalves\HelpDesk\Events\AttachmentRemoved;
use JeffersonGoncalves\HelpDesk\Events\CommentAdded;
use JeffersonGoncalves\HelpDesk\Events\TicketAssigned;
use JeffersonGoncalves\HelpDesk\Events\TicketClosed;
use JeffersonGoncalves\HelpDesk\Events\TicketCreated;
use JeffersonGoncalves\HelpDesk\Events\TicketPriorityChanged;
use JeffersonGoncalves\HelpDesk\Events\TicketReopened;
use JeffersonGoncalves\HelpDesk\Events\TicketStatusChanged;
use JeffersonGoncalves\HelpDesk\Models\TicketHistory;

class LogTicketHistory
{
    public function handleTicketCreated(TicketCreated $event): void
    {
        TicketHistory::create([
            'ticket_id' => $event->ticket->id,
            'performer_type' => $event->ticket->user_type,
            'performer_id' => $event->ticket->user_id,
            'action' => HistoryAction::Created,
            'description' => 'Ticket created.',
        ]);
    }

    public function handleTicketStatusChanged(TicketStatusChanged $event): void
    {
        TicketHistory::create([
            'ticket_id' => $event->ticket->id,
            'performer_type' => $event->performer?->getMorphClass(),
            'performer_id' => $event->performer?->getKey(),
            'action' => HistoryAction::StatusChanged,
            'field' => 'status',
            'old_value' => $event->oldStatus->value,
            'new_value' => $event->newStatus->value,
            'description' => "Status changed from {$event->oldStatus->value} to {$event->newStatus->value}.",
        ]);
    }

    public function handleTicketPriorityChanged(TicketPriorityChanged $event): void
    {
        TicketHistory::create([
            'ticket_id' => $event->ticket->id,
            'performer_type' => $event->performer?->getMorphClass(),
            'performer_id' => $event->performer?->getKey(),
            'action' => HistoryAction::PriorityChanged,
            'field' => 'priority',
            'old_value' => $event->oldPriority->value,
            'new_value' => $event->newPriority->value,
            'description' => "Priority changed from {$event->oldPriority->value} to {$event->newPriority->value}.",
        ]);
    }

    public function handleTicketAssigned(TicketAssigned $event): void
    {
        TicketHistory::create([
            'ticket_id' => $event->ticket->id,
            'performer_type' => $event->assignedBy?->getMorphClass(),
            'performer_id' => $event->assignedBy?->getKey(),
            'action' => HistoryAction::Assigned,
            'field' => 'assigned_to',
            'new_value' => $event->assignedTo->getKey(),
            'description' => 'Ticket assigned.',
        ]);
    }

    public function handleTicketClosed(TicketClosed $event): void
    {
        TicketHistory::create([
            'ticket_id' => $event->ticket->id,
            'performer_type' => $event->closedBy?->getMorphClass(),
            'performer_id' => $event->closedBy?->getKey(),
            'action' => HistoryAction::Closed,
            'description' => 'Ticket closed.',
        ]);
    }

    public function handleTicketReopened(TicketReopened $event): void
    {
        TicketHistory::create([
            'ticket_id' => $event->ticket->id,
            'performer_type' => $event->reopenedBy?->getMorphClass(),
            'performer_id' => $event->reopenedBy?->getKey(),
            'action' => HistoryAction::Reopened,
            'description' => 'Ticket reopened.',
        ]);
    }

    public function handleCommentAdded(CommentAdded $event): void
    {
        TicketHistory::create([
            'ticket_id' => $event->ticket->id,
            'performer_type' => $event->comment->author_type,
            'performer_id' => $event->comment->author_id,
            'action' => HistoryAction::CommentAdded,
            'description' => "Comment added (type: {$event->comment->type->value}).",
            'metadata' => ['comment_id' => $event->comment->id],
        ]);
    }

    public function handleAttachmentAdded(AttachmentAdded $event): void
    {
        TicketHistory::create([
            'ticket_id' => $event->ticket->id,
            'performer_type' => $event->attachment->uploaded_by_type,
            'performer_id' => $event->attachment->uploaded_by_id,
            'action' => HistoryAction::AttachmentAdded,
            'description' => "Attachment added: {$event->attachment->file_name}.",
            'metadata' => ['attachment_id' => $event->attachment->id],
        ]);
    }

    public function handleAttachmentRemoved(AttachmentRemoved $event): void
    {
        TicketHistory::create([
            'ticket_id' => $event->ticket->id,
            'performer_type' => $event->removedBy?->getMorphClass(),
            'performer_id' => $event->removedBy?->getKey(),
            'action' => HistoryAction::AttachmentRemoved,
            'description' => "Attachment removed: {$event->attachment->file_name}.",
            'metadata' => ['attachment_id' => $event->attachment->id],
        ]);
    }

    public function subscribe($events): array
    {
        return [
            TicketCreated::class => 'handleTicketCreated',
            TicketStatusChanged::class => 'handleTicketStatusChanged',
            TicketPriorityChanged::class => 'handleTicketPriorityChanged',
            TicketAssigned::class => 'handleTicketAssigned',
            TicketClosed::class => 'handleTicketClosed',
            TicketReopened::class => 'handleTicketReopened',
            CommentAdded::class => 'handleCommentAdded',
            AttachmentAdded::class => 'handleAttachmentAdded',
            AttachmentRemoved::class => 'handleAttachmentRemoved',
        ];
    }
}
