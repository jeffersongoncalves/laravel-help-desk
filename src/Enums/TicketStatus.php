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

    /**
     * The linear happy-path a visual stepper renders, as opposed to
     * allowedTransitions()'s branching, loopable graph. Pending and OnHold
     * are suspensions of this line, not steps on it — see pipelineStep().
     *
     * @return array<TicketStatus>
     */
    public static function pipelineSteps(): array
    {
        return [self::Open, self::InProgress, self::Resolved, self::Closed];
    }

    /**
     * Which pipelineSteps() entry this status sits at for stepper display.
     * Pending/OnHold map onto InProgress, since the ticket is still being
     * worked, just currently waiting on someone else.
     */
    public function pipelineStep(): self
    {
        return match ($this) {
            self::Pending, self::OnHold => self::InProgress,
            default => $this,
        };
    }
}
