<?php

namespace JeffersonGoncalves\HelpDesk\Listeners;

use JeffersonGoncalves\HelpDesk\Events\TicketStatusChanged;
use JeffersonGoncalves\HelpDesk\Services\SlaService;

class TrackSlaPause
{
    public function __construct(
        protected SlaService $slaService,
    ) {}

    public function handle(TicketStatusChanged $event): void
    {
        $this->slaService->trackPause($event->ticket, $event->oldStatus, $event->newStatus);
    }
}
