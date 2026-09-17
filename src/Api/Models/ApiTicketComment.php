<?php

namespace JeffersonGoncalves\HelpDesk\Api\Models;

use JeffersonGoncalves\HelpDesk\Models\TicketComment;

class ApiTicketComment extends TicketComment
{
    use GuardsRelations;

    /**
     * @return array<string, string>
     */
    protected function apiAlternatives(): array
    {
        return [
            'ticket' => 'Keep the ticket you already have, or fetch it with HelpDesk::tickets()->findByUuid($uuid).',
            'author' => 'Use $comment->author_name and $comment->author_email.',
            'attachments' => 'The show endpoint returns them: use HelpDesk::tickets()->findByUuid($uuid).',
        ];
    }
}
