<?php

namespace JeffersonGoncalves\HelpDesk\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JeffersonGoncalves\HelpDesk\Models\TicketComment;

/**
 * @property TicketComment $resource
 */
class TicketCommentResource extends JsonResource
{
    /**
     * `is_internal` is deliberately absent rather than always false: the API
     * never returns an internal note, so publishing the flag would only invite
     * a caller to ask for one.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'body' => $this->resource->body,
            'type' => $this->resource->type->value,
            'author_name' => $this->resource->author_name,
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
