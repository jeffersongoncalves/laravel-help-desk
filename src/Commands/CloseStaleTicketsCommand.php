<?php

namespace JeffersonGoncalves\HelpDesk\Commands;

use Illuminate\Console\Command;
use JeffersonGoncalves\HelpDesk\Enums\TicketStatus;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Services\TicketService;

class CloseStaleTicketsCommand extends Command
{
    protected $signature = 'help-desk:close-stale
                            {--days= : Number of days of inactivity (default from config)}
                            {--status=resolved : Only close tickets with this status}
                            {--dry-run : Show what would be closed without closing}';

    protected $description = 'Automatically close stale tickets after a period of inactivity';

    public function __construct(
        protected TicketService $ticketService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $days = $this->option('days')
            ? (int) $this->option('days')
            : config('help-desk.ticket.auto_close_days');

        if (! $days) {
            $this->info('Auto-close is not configured. Set help-desk.ticket.auto_close_days or use --days.');

            return self::SUCCESS;
        }

        $status = $this->option('status');
        $dryRun = $this->option('dry-run');

        $query = Ticket::where('status', $status)
            ->where('updated_at', '<', now()->subDays($days));

        $tickets = $query->get();

        if ($tickets->isEmpty()) {
            $this->info('No stale tickets found.');

            return self::SUCCESS;
        }

        $this->info("Found {$tickets->count()} stale ticket(s).");

        foreach ($tickets as $ticket) {
            if ($dryRun) {
                $this->line("  Would close: {$ticket->reference_number} - {$ticket->title}");
            } else {
                $this->ticketService->close($ticket);
                $this->line("  Closed: {$ticket->reference_number} - {$ticket->title}");
            }
        }

        if ($dryRun) {
            $this->warn('Dry run - no tickets were actually closed.');
        } else {
            $this->info("Closed {$tickets->count()} stale ticket(s).");
        }

        return self::SUCCESS;
    }
}
