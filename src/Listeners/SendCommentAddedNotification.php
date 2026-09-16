<?php

namespace JeffersonGoncalves\HelpDesk\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use JeffersonGoncalves\HelpDesk\Events\CommentAdded;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Models\TicketWatcher;
use JeffersonGoncalves\HelpDesk\Notifications\NewCommentNotification;

class SendCommentAddedNotification implements ShouldQueue
{
    public function handle(CommentAdded $event): void
    {
        if (! config('help-desk.notifications.notify_on.comment_added', true)) {
            return;
        }

        if ($event->comment->is_internal) {
            return;
        }

        $ticket = $event->ticket;
        $comment = $event->comment;

        // Notify the ticket owner if the comment is not by them. Compared on
        // the stored keys rather than a loaded model, so this holds even when
        // the requester belongs to another application.
        if ($comment->author_type !== $ticket->user_type || $comment->author_id !== $ticket->user_id) {
            $ticket->notifyRequester(new NewCommentNotification($ticket, $comment));
        }

        // Notify watchers. Only watchers whose model exists here can be eager
        // loaded — instantiating an unknown morph type is fatal.
        $ticket->loadMissing('watchers');

        $watchers = $ticket->watchers
            ->filter(fn (TicketWatcher $pivot): bool => Ticket::morphIsResolvable($pivot->watcher_type));

        $watchers->loadMissing('watcher');

        foreach ($watchers as $watcherPivot) {
            /** @var Model|null $watcher */
            $watcher = $watcherPivot->watcher;
            if ($watcher && method_exists($watcher, 'notify')) {
                if ($comment->author_type !== $watcher->getMorphClass() || $comment->author_id !== $watcher->getKey()) {
                    $watcher->notify(new NewCommentNotification($ticket, $comment));
                }
            }
        }
    }
}
