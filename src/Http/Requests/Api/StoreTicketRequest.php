<?php

namespace JeffersonGoncalves\HelpDesk\Http\Requests\Api;

use Illuminate\Validation\Rule;
use JeffersonGoncalves\HelpDesk\Enums\TicketPriority;

class StoreTicketRequest extends SignedApiRequest
{
    /**
     * @return array<string, mixed>
     */
    protected function payloadRules(): array
    {
        return [
            'department_id' => ['required', 'integer', Rule::exists('help_desk_departments', 'id')->where('is_active', true)],
            'category_id' => ['nullable', 'integer', Rule::exists('help_desk_categories', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'priority' => ['nullable', Rule::enum(TicketPriority::class)],
            // Asserted by the satellite, like the actor it belongs to. It never
            // widens what the API returns: reads stay scoped to the requester.
            'company_id' => ['nullable', 'string', 'max:64'],
            'company_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Only the fields a requester is allowed to set. Status, assignment,
     * app_key and the rest are the server's to decide.
     *
     * @return array<string, mixed>
     */
    public function ticketAttributes(): array
    {
        return array_filter([
            'department_id' => $this->validated()['department_id'],
            'category_id' => $this->validated()['category_id'] ?? null,
            'title' => $this->validated()['title'],
            'description' => $this->validated()['description'],
            'priority' => $this->validated()['priority'] ?? null,
            'company_id' => $this->validated()['company_id'] ?? null,
            'company_name' => $this->validated()['company_name'] ?? null,
            'source' => 'api',
        ], fn ($value) => $value !== null);
    }
}
