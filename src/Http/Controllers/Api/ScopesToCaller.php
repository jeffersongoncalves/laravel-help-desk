<?php

namespace JeffersonGoncalves\HelpDesk\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use JeffersonGoncalves\HelpDesk\Http\Middleware\VerifyHelpDeskSignature;
use JeffersonGoncalves\HelpDesk\Models\Ticket;

/**
 * Every read the API serves is narrowed to the caller, in one place.
 *
 * Two conditions, not one. The app key stops a satellite reading another
 * application's tickets; the actor stops it reading another of its own users'.
 * Dropping either would be a leak, so they are applied together and nowhere
 * else.
 */
trait ScopesToCaller
{
    /**
     * @return Builder<Ticket>
     */
    protected function scoped(Request $request): Builder
    {
        $actor = $request->input('actor', []);

        return Ticket::query()
            ->where('app_key', $request->attributes->get(VerifyHelpDeskSignature::ATTRIBUTE))
            ->where('user_type', is_array($actor) ? ($actor['type'] ?? null) : null)
            ->where('user_id', is_array($actor) ? ($actor['id'] ?? null) : null);
    }
}
