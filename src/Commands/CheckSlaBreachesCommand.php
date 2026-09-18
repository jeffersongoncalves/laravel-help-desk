<?php

namespace JeffersonGoncalves\HelpDesk\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use JeffersonGoncalves\HelpDesk\Events\TicketSlaFirstResponseBreached;
use JeffersonGoncalves\HelpDesk\Events\TicketSlaResolutionBreached;
use JeffersonGoncalves\HelpDesk\Models\Ticket;

class CheckSlaBreachesCommand extends Command
{
    protected $signature = 'help-desk:check-sla-breaches
                            {--dry-run : Show what would be flagged without dispatching events}';

    protected $description = 'Flag open tickets whose SLA first-response or resolution due date has passed';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $firstResponseBreaches = $this->firstResponseBreachQuery()->get();

        foreach ($firstResponseBreaches as $ticket) {
            if ($dryRun) {
                $this->line("  Would flag first-response breach: {$ticket->reference_number}");

                continue;
            }

            $ticket->update(['sla_first_response_breached_at' => now()]);
            event(new TicketSlaFirstResponseBreached($ticket));
            $this->line("  Flagged first-response breach: {$ticket->reference_number}");
        }

        $resolutionBreaches = $this->resolutionBreachQuery()->get();

        foreach ($resolutionBreaches as $ticket) {
            if ($dryRun) {
                $this->line("  Would flag resolution breach: {$ticket->reference_number}");

                continue;
            }

            $ticket->update(['sla_resolution_breached_at' => now()]);
            event(new TicketSlaResolutionBreached($ticket));
            $this->line("  Flagged resolution breach: {$ticket->reference_number}");
        }

        if ($dryRun) {
            $this->warn('Dry run - no tickets were actually flagged.');
        }

        $this->info(sprintf(
            'Checked SLA breaches: %d first-response, %d resolution.',
            $firstResponseBreaches->count(),
            $resolutionBreaches->count()
        ));

        return self::SUCCESS;
    }

    /**
     * Open, past its first-response due date, never responded to, not
     * already flagged.
     *
     * @return Builder<Ticket>
     */
    protected function firstResponseBreachQuery(): Builder
    {
        return Ticket::open()
            ->whereNotNull('sla_first_response_due_at')
            ->where('sla_first_response_due_at', '<', now())
            ->whereNull('first_response_at')
            ->whereNull('sla_first_response_breached_at');
    }

    /**
     * Open, past its resolution due date, not currently paused, not already
     * flagged. A paused ticket is excluded even if its due date has passed --
     * the clock isn't running.
     *
     * @return Builder<Ticket>
     */
    protected function resolutionBreachQuery(): Builder
    {
        return Ticket::open()
            ->whereNotNull('sla_resolution_due_at')
            ->where('sla_resolution_due_at', '<', now())
            ->whereNull('sla_paused_at')
            ->whereNull('sla_resolution_breached_at');
    }
}
