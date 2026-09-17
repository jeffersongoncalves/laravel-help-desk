<?php

namespace JeffersonGoncalves\HelpDesk\Http\Requests\Api;

class StoreCommentRequest extends SignedApiRequest
{
    /**
     * @return array<string, mixed>
     */
    protected function payloadRules(): array
    {
        return [
            'body' => ['required', 'string'],
        ];
    }
}
