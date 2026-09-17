<?php

namespace JeffersonGoncalves\HelpDesk\Api\Models;

use JeffersonGoncalves\HelpDesk\Exceptions\HelpDeskApiException;

/**
 * Makes a relation on an API-hydrated model fail legibly.
 *
 * Without this, `$ticket->comments` on a satellite queries a table the
 * application does not have, and the user sees a raw SQL error naming
 * `help_desk_ticket_comments`. That is the same unhelpful failure the identity
 * snapshots were built to remove, so it gets the same treatment: say what
 * happened and what to do instead.
 *
 * Subclasses rather than a flag on the shared models, so the database driver's
 * behaviour is untouched — there is no branch in the hot path that could be got
 * wrong.
 *
 * A relation the response already filled is returned as normal. Only reaching
 * for one that was never sent throws.
 */
trait GuardsRelations
{
    /**
     * What to reach for instead, per relation.
     *
     * @return array<string, string>
     */
    abstract protected function apiAlternatives(): array;

    public function getRelationValue($key)
    {
        if ($this->isRelation($key) && ! $this->relationLoaded($key)) {
            throw HelpDeskApiException::relationUnavailable(
                static::class,
                $key,
                $this->apiAlternatives()[$key] ?? null,
            );
        }

        return parent::getRelationValue($key);
    }

    /**
     * Saving would write to a table this application does not have.
     */
    public function save(array $options = []): bool
    {
        throw HelpDeskApiException::operatorOnly(class_basename(static::class).'::save()');
    }

    public function delete(): bool
    {
        throw HelpDeskApiException::operatorOnly(class_basename(static::class).'::delete()');
    }
}
