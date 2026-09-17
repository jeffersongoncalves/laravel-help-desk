<?php

namespace JeffersonGoncalves\HelpDesk\Listeners;

use JeffersonGoncalves\HelpDesk\Events\CommentAdded;
use JeffersonGoncalves\HelpDesk\Services\SlaService;

class RecordFirstResponse
{
    public function __construct(
        protected SlaService $slaService,
    ) {}

    public function handle(CommentAdded $event): void
    {
        $this->slaService->recordFirstResponse($event->ticket, $event->comment);
    }
}
