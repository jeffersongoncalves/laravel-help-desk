<?php

namespace JeffersonGoncalves\HelpDesk\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JeffersonGoncalves\HelpDesk\Models\InboundEmail;

class InboundEmailProcessed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly InboundEmail $inboundEmail,
    ) {}
}
