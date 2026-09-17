<?php

namespace JeffersonGoncalves\HelpDesk\Services;

use Illuminate\Database\Eloquent\Model;
use JeffersonGoncalves\HelpDesk\Events\TicketFeedbackSubmitted;
use JeffersonGoncalves\HelpDesk\Exceptions\TicketFeedbackNotAllowedException;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Models\TicketFeedback;

class FeedbackService
{
    /**
     * @throws TicketFeedbackNotAllowedException when the rating is out of
     *                                           range, or the ticket cannot receive feedback right now
     */
    public function submit(Ticket $ticket, Model $user, int $rating, ?string $comment = null): TicketFeedback
    {
        if ($rating < 1 || $rating > 5) {
            throw TicketFeedbackNotAllowedException::invalidRating($rating);
        }

        if (! $ticket->canReceiveFeedback()) {
            throw TicketFeedbackNotAllowedException::make();
        }

        $feedback = $ticket->feedback()->create([
            'rating' => $rating,
            'comment' => $comment,
            'submitted_by_type' => $user->getMorphClass(),
            'submitted_by_id' => $user->getKey(),
            'metadata' => ['submitter' => TicketFeedback::snapshotOf($user)],
        ]);

        event(new TicketFeedbackSubmitted($ticket, $feedback));

        return $feedback;
    }
}
