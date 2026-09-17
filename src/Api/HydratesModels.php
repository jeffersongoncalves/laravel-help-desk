<?php

namespace JeffersonGoncalves\HelpDesk\Api;

use Illuminate\Database\Eloquent\Model;
use JeffersonGoncalves\HelpDesk\Api\Models\ApiTicket;
use JeffersonGoncalves\HelpDesk\Api\Models\ApiTicketComment;

/**
 * Turns a response payload back into the models the contracts promise.
 *
 * `exists` is set so accessors, enum casts and the is*() helpers behave as
 * they do on the database driver, and `syncOriginal` so nothing looks dirty.
 * What it is not is persisted — see GuardsRelations.
 */
trait HydratesModels
{
    /**
     * @param  array<string, mixed>  $payload
     */
    protected function hydrateTicket(array $payload): ApiTicket
    {
        $comments = $payload['comments'] ?? null;
        unset($payload['comments']);

        /** @var ApiTicket $ticket */
        $ticket = $this->hydrate(new ApiTicket, $payload);

        // Only set the relation when the response carried it. Leaving it unset
        // is what makes reading it throw something readable rather than
        // silently returning an empty collection the caller will trust.
        if (is_array($comments)) {
            $ticket->setRelation('comments', collect($comments)->map(
                fn (array $comment) => $this->hydrateComment($comment),
            ));
        }

        return $ticket;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function hydrateComment(array $payload): ApiTicketComment
    {
        /** @var ApiTicketComment $comment */
        $comment = $this->hydrate(new ApiTicketComment, $payload);

        return $comment;
    }

    /**
     * The identity snapshot is rebuilt from the flat fields the resource sends,
     * so the accessors that read it keep working on the satellite.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function hydrate(Model $model, array $payload): Model
    {
        $metadata = array_filter([
            'requester' => array_filter([
                'name' => $payload['requester_name'] ?? null,
                'email' => $payload['requester_email'] ?? null,
            ], fn ($value) => filled($value)),
            'author' => array_filter([
                'name' => $payload['author_name'] ?? null,
                'email' => $payload['author_email'] ?? null,
            ], fn ($value) => filled($value)),
        ], fn ($snapshot) => $snapshot !== []);

        unset(
            $payload['requester_name'], $payload['requester_email'],
            $payload['author_name'], $payload['author_email'],
        );

        if ($metadata !== []) {
            $payload['metadata'] = $metadata;
        }

        $model->forceFill($payload);
        $model->exists = true;
        $model->syncOriginal();

        return $model;
    }
}
