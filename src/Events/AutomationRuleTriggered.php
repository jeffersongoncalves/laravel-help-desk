<?php

namespace JeffersonGoncalves\HelpDesk\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JeffersonGoncalves\HelpDesk\Models\AutomationRule;
use JeffersonGoncalves\HelpDesk\Models\Ticket;

class AutomationRuleTriggered
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $action
     */
    public function __construct(
        public readonly AutomationRule $rule,
        public readonly Ticket $ticket,
        public readonly array $action,
    ) {}
}
