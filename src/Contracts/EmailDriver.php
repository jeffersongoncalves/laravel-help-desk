<?php

namespace JeffersonGoncalves\HelpDesk\Contracts;

use JeffersonGoncalves\HelpDesk\Models\EmailChannel;

interface EmailDriver
{
    public function poll(EmailChannel $channel): array;

    public function getDriverName(): string;

    /**
     * Verify the channel's settings work, without fetching or altering any
     * message. Used by a "test connection" action before the channel is
     * trusted to run unattended.
     *
     * @return array{success: bool, message: string}
     */
    public function testConnection(EmailChannel $channel): array;
}
