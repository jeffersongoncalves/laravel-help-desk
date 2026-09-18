<?php

namespace JeffersonGoncalves\HelpDesk\Services;

use Illuminate\Support\Carbon;
use JeffersonGoncalves\HelpDesk\Enums\TicketStatus;
use JeffersonGoncalves\HelpDesk\Models\SlaPolicy;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use RuntimeException;

class SlaService
{
    /**
     * Days as they appear in a policy's business_hours JSON, keyed by Carbon's
     * own 0 (Sunday) to 6 (Saturday) numbering.
     */
    protected const DAYS = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

    /**
     * Statuses that suspend the resolution SLA clock: the ticket is waiting
     * on the requester or a third party, not sitting idle on the operator.
     */
    protected const PAUSED_STATUSES = [TicketStatus::Pending, TicketStatus::OnHold];

    /**
     * Resolves the most specific matching active policy for the ticket's
     * department and priority, and stamps the ticket with it plus both
     * computed due dates. A no-op, successfully, when nothing matches -- SLA
     * is opt-in, not a ticket requirement.
     */
    public function applyPolicy(Ticket $ticket): Ticket
    {
        $policy = $this->resolvePolicy($ticket);

        if ($policy === null) {
            return $ticket;
        }

        $ticket->sla_policy_id = $policy->id;
        $ticket->sla_first_response_due_at = $this->addBusinessMinutes(
            $ticket->created_at, $policy->first_response_minutes, $policy->business_hours
        );
        $ticket->sla_resolution_due_at = $this->addBusinessMinutes(
            $ticket->created_at, $policy->resolution_minutes, $policy->business_hours
        );
        $ticket->save();

        return $ticket;
    }

    /**
     * Pauses or resumes the resolution SLA clock as the ticket's status
     * changes. Entering Pending/OnHold from an active status starts the
     * pause; leaving either of them for an active status ends it, adding the
     * elapsed time to total_sla_paused_minutes and pushing
     * sla_resolution_due_at forward by the same amount. Moving directly
     * between Pending and OnHold (both paused) is a no-op -- sla_paused_at
     * keeps the time the pause actually started.
     *
     * sla_first_response_due_at is never adjusted here: pausing excuses time
     * the operator is waiting on someone else to resolve the ticket, not the
     * initial acknowledgement.
     */
    public function trackPause(Ticket $ticket, TicketStatus $oldStatus, TicketStatus $newStatus): void
    {
        $wasPaused = in_array($oldStatus, self::PAUSED_STATUSES, true);
        $isPaused = in_array($newStatus, self::PAUSED_STATUSES, true);

        if ($isPaused && ! $wasPaused) {
            $ticket->update(['sla_paused_at' => now()]);

            return;
        }

        if ($wasPaused && ! $isPaused && $ticket->sla_paused_at !== null) {
            $elapsedMinutes = (int) $ticket->sla_paused_at->diffInMinutes(now());

            $ticket->update([
                'total_sla_paused_minutes' => $ticket->total_sla_paused_minutes + $elapsedMinutes,
                'sla_resolution_due_at' => $ticket->sla_resolution_due_at?->copy()->addMinutes($elapsedMinutes),
                'sla_paused_at' => null,
            ]);
        }
    }

    /**
     * Precedence: department+priority, then department only, then priority
     * only, then a fully generic policy. The first active match wins.
     */
    protected function resolvePolicy(Ticket $ticket): ?SlaPolicy
    {
        $base = fn () => SlaPolicy::query()->active();

        return $base()->where('department_id', $ticket->department_id)->where('priority', $ticket->priority->value)->first()
            ?? $base()->where('department_id', $ticket->department_id)->whereNull('priority')->first()
            ?? $base()->whereNull('department_id')->where('priority', $ticket->priority->value)->first()
            ?? $base()->whereNull('department_id')->whereNull('priority')->first();
    }

    /**
     * Adds $minutes to $start. A null $businessHours is a straight 24/7
     * addition; otherwise only minutes inside the declared weekly windows
     * count, and the result rolls forward past closed days/hours.
     *
     * $businessHours shape: {"mon": ["09:00", "18:00"], ...} in UTC (v1 has
     * no timezone or holiday-calendar support). A day absent from the map is
     * closed all day.
     *
     * @param  array<string, array{0: string, 1: string}>|null  $businessHours
     */
    protected function addBusinessMinutes(Carbon $start, int $minutes, ?array $businessHours): Carbon
    {
        if ($businessHours === null || $businessHours === []) {
            return $start->copy()->addMinutes($minutes);
        }

        $cursor = $start->copy();
        $remaining = $minutes;

        // A calendar year of iterations is more than enough to either finish
        // or prove the configuration has no open day at all.
        for ($guard = 0; $guard < 366; $guard++) {
            $window = $businessHours[self::DAYS[$cursor->dayOfWeek]] ?? null;

            if ($window === null) {
                $cursor = $cursor->copy()->startOfDay()->addDay();

                continue;
            }

            [$open, $close] = $this->windowOn($cursor, $window);

            if ($cursor->lt($open)) {
                $cursor = $open->copy();
            }

            if ($cursor->gte($close)) {
                $cursor = $cursor->copy()->startOfDay()->addDay();

                continue;
            }

            $available = (int) $cursor->diffInMinutes($close);

            if ($remaining <= $available) {
                return $cursor->copy()->addMinutes($remaining);
            }

            $remaining -= $available;
            $cursor = $cursor->copy()->startOfDay()->addDay();
        }

        throw new RuntimeException('SLA business_hours has no open day -- cannot compute a due date.');
    }

    /**
     * @param  array{0: string, 1: string}  $window
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function windowOn(Carbon $day, array $window): array
    {
        [$openTime, $closeTime] = $window;

        return [
            Carbon::parse($day->toDateString().' '.$openTime),
            Carbon::parse($day->toDateString().' '.$closeTime),
        ];
    }
}
