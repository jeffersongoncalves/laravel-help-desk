<?php

namespace JeffersonGoncalves\HelpDesk\Commands;

use Illuminate\Console\Command;
use JeffersonGoncalves\HelpDesk\Services\AutomationService;

class RunAutomationsCommand extends Command
{
    protected $signature = 'help-desk:run-automations
                            {--dry-run : Show what would be applied without applying it}';

    protected $description = 'Evaluate active automation rules and apply their actions to matching tickets';

    public function __construct(
        protected AutomationService $automationService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $applied = $this->automationService->evaluate($dryRun);

        foreach ($applied as $entry) {
            $verb = $dryRun ? 'Would apply' : 'Applied';
            $this->line("  {$verb} [{$entry['action']}] from rule \"{$entry['rule']}\" to {$entry['ticket']}");
        }

        if ($dryRun) {
            $this->warn('Dry run - no actions were actually applied.');
        }

        $this->info(sprintf('Automations checked: %d action(s) %s.', count($applied), $dryRun ? 'matched' : 'applied'));

        return self::SUCCESS;
    }
}
