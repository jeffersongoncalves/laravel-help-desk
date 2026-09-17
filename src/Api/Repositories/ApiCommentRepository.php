<?php

namespace JeffersonGoncalves\HelpDesk\Api\Repositories;

use Illuminate\Database\Eloquent\Model;
use JeffersonGoncalves\HelpDesk\Api\HelpDeskClient;
use JeffersonGoncalves\HelpDesk\Api\HydratesModels;
use JeffersonGoncalves\HelpDesk\Api\ResolvesActor;
use JeffersonGoncalves\HelpDesk\Contracts\CommentRepository;
use JeffersonGoncalves\HelpDesk\Enums\CommentType;
use JeffersonGoncalves\HelpDesk\Exceptions\HelpDeskApiException;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Models\TicketComment;

class ApiCommentRepository implements CommentRepository
{
    use HydratesModels, ResolvesActor;

    public function __construct(protected HelpDeskClient $client) {}

    public function addReply(Ticket $ticket, Model $author, string $body, array $options = []): TicketComment
    {
        if (! empty($options['attachments'])) {
            throw HelpDeskApiException::operatorOnly('Attaching a file over the API');
        }

        $payload = $this->client->post("tickets/{$ticket->uuid}/comments", [
            'actor' => $this->actorPayload($author),
            'body' => $body,
        ]);

        return $this->hydrateComment($payload['data']);
    }

    /**
     * An internal note belongs to the operator side. The endpoint would refuse
     * it anyway, so say so here rather than making a round trip to find out.
     */
    public function addNote(Ticket $ticket, Model $author, string $body, array $options = []): TicketComment
    {
        throw HelpDeskApiException::operatorOnly('addNote()');
    }

    public function addSystemComment(Ticket $ticket, string $body, array $options = []): TicketComment
    {
        throw HelpDeskApiException::operatorOnly('addSystemComment()');
    }

    public function addComment(Ticket $ticket, Model $author, string $body, CommentType $type, array $options = []): TicketComment
    {
        if ($type !== CommentType::Reply) {
            throw HelpDeskApiException::operatorOnly("addComment() with type {$type->value}");
        }

        return $this->addReply($ticket, $author, $body, $options);
    }

    public function delete(TicketComment $comment): bool
    {
        throw HelpDeskApiException::operatorOnly('deleting a comment');
    }
}
