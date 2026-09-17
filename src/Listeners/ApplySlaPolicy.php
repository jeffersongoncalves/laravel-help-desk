<?php

namespace JeffersonGoncalves\HelpDesk\Listeners;

use JeffersonGoncalves\HelpDesk\Events\TicketCreated;
use JeffersonGoncalves\HelpDesk\Services\SlaService;

class ApplySlaPolicy
{
    public function __construct(
        protected SlaService $slaService,
    ) {}

    public function handle(TicketCreated $event): void
    {
        $this->slaService->applyPolicy($event->ticket);
    }
}
