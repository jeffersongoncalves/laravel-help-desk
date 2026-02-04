<?php

namespace JeffersonGoncalves\HelpDesk\Exceptions;

use JeffersonGoncalves\HelpDesk\Enums\TicketStatus;
use RuntimeException;

class InvalidStatusTransitionException extends RuntimeException
{
    public static function make(TicketStatus $from, TicketStatus $to): self
    {
        return new self(
            __('help-desk::tickets.validation.status_transition_invalid', [
                'from' => $from->label(),
                'to' => $to->label(),
            ])
        );
    }
}
