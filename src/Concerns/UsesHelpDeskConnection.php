<?php

namespace JeffersonGoncalves\HelpDesk\Concerns;

/**
 * Routes the model to the connection configured in `help-desk.connection`.
 *
 * Leaving that config null keeps the application's default connection, which
 * is the single-application setup. Pointing it at another connection lets
 * several applications share one help desk database — see the "Sharing one
 * help desk database" section of the README.
 */
trait UsesHelpDeskConnection
{
    public function getConnectionName(): ?string
    {
        // An explicit setConnection() call still wins, so callers can target a
        // specific connection for a single query or a single model instance.
        return $this->connection ?? config('help-desk.connection');
    }
}
