<?php

namespace JeffersonGoncalves\HelpDesk\Api\Models;

use JeffersonGoncalves\HelpDesk\Models\Ticket;

/**
 * A ticket that came back over the API.
 *
 * Still a Ticket, so calling code and type hints do not change: accessors,
 * enum casts and the is*() helpers all work. What it does not have is a
 * database, so see GuardsRelations.
 */
class ApiTicket extends Ticket
{
    use GuardsRelations;

    /**
     * @return array<string, string>
     */
    protected function apiAlternatives(): array
    {
        return [
            'comments' => 'The show endpoint returns them: use HelpDesk::tickets()->findByUuid($uuid).',
            'department' => 'Read $ticket->department_id, or list departments with HelpDesk::departments()->all().',
            'category' => 'Read $ticket->category_id.',
            'user' => 'Use $ticket->requester_name and $ticket->requester_email.',
            'assignedTo' => 'Assignment is an operator concern and is not exposed to satellites.',
            'attachments' => 'The show endpoint returns them: use HelpDesk::tickets()->findByUuid($uuid).',
            'history' => 'History is an operator concern and is not exposed to satellites.',
            'watchers' => 'Watchers are not exposed over the API.',
        ];
    }
}
