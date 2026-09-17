<?php

namespace JeffersonGoncalves\HelpDesk\Api;

use Illuminate\Database\Eloquent\Model;
use JeffersonGoncalves\HelpDesk\Api\Models\ApiTicket;
use JeffersonGoncalves\HelpDesk\Api\Models\ApiTicketAttachment;
use JeffersonGoncalves\HelpDesk\Api\Models\ApiTicketComment;
use JeffersonGoncalves\HelpDesk\Models\Ticket;

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
     * Declared as Ticket, not ApiTicket, so a collection of these is a
     * collection of what the contract promises. The object is still an
     * ApiTicket — the subtype is an implementation detail of this transport,
     * and nothing outside it should be typed on the difference.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function hydrateTicket(array $payload): Ticket
    {
        $comments = $payload['comments'] ?? null;
        $attachments = $payload['attachments'] ?? null;
        unset($payload['comments'], $payload['attachments']);

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

        // Lifted out of the payload for the same reason comments are: left in,
        // forceFill() writes an *attribute* named `attachments` holding arrays,
        // getAttribute() finds it before GuardsRelations is ever consulted, and
        // the caller gets array where the contract says Collection.
        if (is_array($attachments)) {
            $attachments = collect($attachments)->map(
                fn (array $attachment) => $this->hydrateAttachment($attachment),
            );

            $ticket->setRelation('attachments', $attachments);

            // The list is flat and carries comment_id, so the per-comment
            // subsets come from it — TicketCommentResource does not nest them,
            // and the data to fill them arrived in the same response.
            if ($ticket->relationLoaded('comments')) {
                $byComment = $attachments->groupBy('comment_id');

                $ticket->getRelation('comments')->each(fn (ApiTicketComment $comment) => $comment->setRelation(
                    'attachments',
                    $byComment->get($comment->getKey(), collect()),
                ));
            }
        }

        return $ticket;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function hydrateAttachment(array $payload): ApiTicketAttachment
    {
        /** @var ApiTicketAttachment $attachment */
        $attachment = $this->hydrate(new ApiTicketAttachment, $payload);

        return $attachment;
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
            'uploader' => array_filter([
                'name' => $payload['uploader_name'] ?? null,
                'email' => $payload['uploader_email'] ?? null,
            ], fn ($value) => filled($value)),
        ], fn ($snapshot) => $snapshot !== []);

        unset(
            $payload['requester_name'], $payload['requester_email'],
            $payload['author_name'], $payload['author_email'],
            $payload['uploader_name'], $payload['uploader_email'],
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
