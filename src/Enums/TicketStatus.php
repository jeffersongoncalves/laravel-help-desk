<?php

namespace JeffersonGoncalves\HelpDesk\Enums;

enum TicketStatus: string
{
    case Open = 'open';
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case OnHold = 'on_hold';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return __('help-desk::statuses.'.$this->value);
    }

    /**
     * @return array<TicketStatus>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open => [self::Pending, self::InProgress, self::OnHold, self::Resolved, self::Closed],
            self::Pending => [self::Open, self::InProgress, self::OnHold, self::Resolved, self::Closed],
            self::InProgress => [self::Pending, self::OnHold, self::Resolved, self::Closed],
            self::OnHold => [self::Open, self::Pending, self::InProgress, self::Resolved, self::Closed],
            self::Resolved => [self::Open, self::Closed],
            self::Closed => config('help-desk.ticket.allow_reopen', true) ? [self::Open] : [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions());
    }
}
