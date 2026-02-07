<?php

namespace JeffersonGoncalves\HelpDesk\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use JeffersonGoncalves\HelpDesk\Events\CommentAdded;
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
        $user = $ticket->user;

        // Notify the ticket owner if the comment is not by them
        if ($user && method_exists($user, 'notify')) {
            if ($comment->author_type !== $user->getMorphClass() || $comment->author_id !== $user->getKey()) {
                $user->notify(new NewCommentNotification($ticket, $comment));
            }
        }

        // Notify watchers
        foreach ($ticket->watchers as $watcherPivot) {
            /** @var \Illuminate\Database\Eloquent\Model|null $watcher */
            $watcher = $watcherPivot->watcher;
            if ($watcher && method_exists($watcher, 'notify')) {
                if ($comment->author_type !== $watcher->getMorphClass() || $comment->author_id !== $watcher->getKey()) {
                    $watcher->notify(new NewCommentNotification($ticket, $comment));
                }
            }
        }
    }
}
