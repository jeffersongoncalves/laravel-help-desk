<?php

namespace JeffersonGoncalves\HelpDesk\Contracts;

use JeffersonGoncalves\HelpDesk\Models\InboundEmail;

interface InboundEmailHandler
{
    public function handle(InboundEmail $inboundEmail): void;
}
