<?php

namespace JeffersonGoncalves\HelpDesk\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use JeffersonGoncalves\HelpDesk\Events\CommentAdded;
use JeffersonGoncalves\HelpDesk\Events\TicketCreated;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Models\TicketWatcher;
use JeffersonGoncalves\HelpDesk\Notifications\OperatorTicketActivityNotification;

/**
 * Emails the people handling a ticket when the requester does something:
 * opens it, or replies to it. The assigned operator if there is one, otherwise
 * every operator of the ticket's department who has not opted out.
 */
class NotifyOperatorsOfTicketActivity implements ShouldQueue
{
    public function handle(TicketCreated|CommentAdded $event): void
    {
        $ticket = $event->ticket;
        $comment = $event instanceof CommentAdded ? $event->comment : null;

        if ($comment === null && ! config('help-desk.notifications.notify_on.operator_new_ticket', true)) {
            return;
        }

        if ($comment !== null) {
            if (! config('help-desk.notifications.notify_on.operator_requester_reply', true) || $comment->is_internal) {
                return;
            }

            // Operator replies already reach the requester; only the other
            // direction is news to the operators.
            if ($comment->author_type !== $ticket->user_type || $comment->author_id !== $ticket->user_id) {
                return;
            }
        }

        $except = [$ticket->user_type.':'.$ticket->user_id];

        if ($comment !== null) {
            // Watchers were already sent the comment by SendCommentAddedNotification.
            $ticket->loadMissing('watchers');
            $except = array_merge($except, $ticket->watchers
                ->map(fn (TicketWatcher $watcher): string => $watcher->watcher_type.':'.$watcher->watcher_id)
                ->all());
        }

        foreach ($this->recipients($ticket, $except) as $operator) {
            if (method_exists($operator, 'notify')) {
                $operator->notify(new OperatorTicketActivityNotification($ticket, $comment));
            }
        }
    }

    /**
     * @param  list<string>  $except
     * @return list<Model>
     */
    protected function recipients(Ticket $ticket, array $except): array
    {
        $department = $ticket->department;

        if ($ticket->isAssigned()) {
            $assignee = $ticket->resolvedAssignedTo();

            if ($assignee === null
                || in_array($assignee->getMorphClass().':'.$assignee->getKey(), $except, true)
                || $department->operatorOptedOutOfEmail($assignee)) {
                return [];
            }

            return [$assignee];
        }

        return $department->operatorsToNotify($except);
    }
}
