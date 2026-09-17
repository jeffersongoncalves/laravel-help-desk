<?php

namespace JeffersonGoncalves\HelpDesk\Services;

use Illuminate\Database\Eloquent\Model;
use JeffersonGoncalves\HelpDesk\Models\CannedResponse;
use JeffersonGoncalves\HelpDesk\Models\Ticket;

class CannedResponseService
{
    /**
     * Substitute the core placeholders a canned response body may contain.
     * An unrecognized placeholder is left untouched in the output.
     */
    public function render(CannedResponse $response, Ticket $ticket, ?Model $agent = null): string
    {
        return strtr($response->body, $this->coreVariables($ticket, $agent));
    }

    /**
     * @return array<string, string>
     */
    protected function coreVariables(Ticket $ticket, ?Model $agent): array
    {
        return [
            '{ticket_code}' => (string) $ticket->reference_number,
            '{user_name}' => (string) ($ticket->requester_name ?? ''),
            '{agent_name}' => (string) ($agent?->getAttribute('name') ?? ''),
            '{department}' => (string) $ticket->department->name,
        ];
    }
}
