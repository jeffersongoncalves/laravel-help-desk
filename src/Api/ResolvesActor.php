<?php

namespace JeffersonGoncalves\HelpDesk\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use JeffersonGoncalves\HelpDesk\Exceptions\HelpDeskApiException;
use JeffersonGoncalves\HelpDesk\Models\Ticket;

/**
 * Who the satellite is acting as.
 *
 * Writes carry the actor explicitly, because the contract passes one. Reads do
 * not, so they fall back to the authenticated user — which on a satellite is
 * the same person the panel is showing tickets to.
 *
 * Deliberately not a config value: an actor in config would be shared by every
 * request the process serves, which in a web application is wrong the moment
 * two people are logged in.
 */
trait ResolvesActor
{
    /**
     * @return array<string, mixed>
     */
    protected function actorPayload(?Model $user = null): array
    {
        $user ??= Auth::user();

        if (! $user instanceof Model) {
            throw HelpDeskApiException::noActor();
        }

        return [
            'type' => $user->getMorphClass(),
            'id' => $user->getKey(),
        ] + Ticket::snapshotOf($user);
    }
}
