<?php

namespace JeffersonGoncalves\HelpDesk\Contracts;

use Illuminate\Database\Eloquent\Model;
use JeffersonGoncalves\HelpDesk\Enums\CommentType;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Models\TicketComment;

/**
 * Where ticket comments live. See TicketRepository for why "repository".
 */
interface CommentRepository
{
    /**
     * A public reply, visible to the requester.
     *
     * @param  array<string, mixed>  $options
     */
    public function addReply(Ticket $ticket, Model $author, string $body, array $options = []): TicketComment;

    /**
     * An internal note, never visible to the requester.
     *
     * @param  array<string, mixed>  $options
     */
    public function addNote(Ticket $ticket, Model $author, string $body, array $options = []): TicketComment;

    /**
     * A comment the package itself wrote, with no author.
     *
     * @param  array<string, mixed>  $options
     */
    public function addSystemComment(Ticket $ticket, string $body, array $options = []): TicketComment;

    /**
     * @param  array<string, mixed>  $options
     */
    public function addComment(Ticket $ticket, Model $author, string $body, CommentType $type, array $options = []): TicketComment;

    public function delete(TicketComment $comment): bool;
}
