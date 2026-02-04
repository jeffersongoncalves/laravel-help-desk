<?php

namespace JeffersonGoncalves\HelpDesk\Commands;

use Illuminate\Console\Command;
use JeffersonGoncalves\HelpDesk\Services\InboundEmailService;

class CleanInboundEmailsCommand extends Command
{
    protected $signature = 'help-desk:clean-emails
                            {--days= : Number of days to retain (default from config)}';

    protected $description = 'Clean old processed inbound emails';

    public function __construct(
        protected InboundEmailService $inboundEmailService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $days = $this->option('days')
            ? (int) $this->option('days')
            : null;

        $deleted = $this->inboundEmailService->cleanOldEmails($days);

        $retentionDays = $days ?? config('help-desk.email.inbound.retention_days', 30);
        $this->info("Deleted {$deleted} inbound email(s) older than {$retentionDays} days.");

        return self::SUCCESS;
    }
}
