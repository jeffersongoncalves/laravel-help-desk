<?php

namespace JeffersonGoncalves\HelpDesk\Contracts;

use JeffersonGoncalves\HelpDesk\Models\EmailChannel;

interface EmailDriver
{
    public function poll(EmailChannel $channel): array;

    public function getDriverName(): string;
}
