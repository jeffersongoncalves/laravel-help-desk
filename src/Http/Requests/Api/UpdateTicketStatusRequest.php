<?php

namespace JeffersonGoncalves\HelpDesk\Http\Requests\Api;

use Illuminate\Validation\Rule;
use JeffersonGoncalves\HelpDesk\Enums\TicketStatus;

/**
 * The two status changes that belong to the person who opened the ticket.
 *
 * Deliberately not a general status setter. `resolved`, `in_progress`,
 * `pending` and `on_hold` carry operator and SLA meaning, and a satellite is
 * not a trust boundary — so the allow-list lives here, in the request the
 * central application validates, and not in the client that sends it.
 */
class UpdateTicketStatusRequest extends SignedApiRequest
{
    /**
     * @var list<string>
     */
    public const ALLOWED = ['closed', 'open'];

    /**
     * @return array<string, mixed>
     */
    protected function payloadRules(): array
    {
        return [
            'status' => ['required', Rule::in(self::ALLOWED)],
        ];
    }

    public function status(): TicketStatus
    {
        return TicketStatus::from($this->validated()['status']);
    }
}
