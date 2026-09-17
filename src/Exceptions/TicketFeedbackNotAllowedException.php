<?php

namespace JeffersonGoncalves\HelpDesk\Exceptions;

use RuntimeException;

class TicketFeedbackNotAllowedException extends RuntimeException
{
    public static function make(): self
    {
        return new self('This ticket cannot receive feedback: it is not closed or resolved, already has feedback, or the feedback window has elapsed.');
    }

    public static function invalidRating(int $rating): self
    {
        return new self("Feedback rating [{$rating}] must be between 1 and 5.");
    }
}
