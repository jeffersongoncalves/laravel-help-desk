<?php

namespace JeffersonGoncalves\HelpDesk\Listeners;

use JeffersonGoncalves\HelpDesk\Events\TicketFeedbackSubmitted;
use JeffersonGoncalves\HelpDesk\Services\CommentService;
use JeffersonGoncalves\HelpDesk\Services\TicketService;

/**
 * Reopens a ticket automatically on a low satisfaction rating. Config-gated
 * (off by default) and only wired up when both it and
 * help-desk.register_default_listeners are enabled.
 *
 * Does not guard the transition itself: canReceiveFeedback() already limits
 * feedback to a Closed or Resolved ticket, so reopen() either succeeds or
 * throws InvalidStatusTransitionException the same way a direct call would
 * (e.g. a Closed ticket with allow_reopen disabled) -- this listener does not
 * swallow that.
 */
class AutoReopenOnLowFeedbackRating
{
    public function __construct(
        protected TicketService $ticketService,
        protected CommentService $commentService,
    ) {}

    public function handle(TicketFeedbackSubmitted $event): void
    {
        if (! config('help-desk.feedback.auto_reopen.enabled', false)) {
            return;
        }

        $threshold = config('help-desk.feedback.auto_reopen.rating_threshold', 1);

        if ($event->feedback->rating > $threshold) {
            return;
        }

        $ticket = $event->ticket;

        $this->ticketService->reopen($ticket);

        $this->commentService->addSystemComment(
            $ticket,
            config('help-desk.feedback.auto_reopen.comment', 'Ticket automatically reopened due to a low satisfaction rating.')
        );
    }
}
