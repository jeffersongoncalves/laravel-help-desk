<?php

namespace JeffersonGoncalves\HelpDesk\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JeffersonGoncalves\HelpDesk\Models\Ticket;

/**
 * @property Ticket $resource
 */
class TicketResource extends JsonResource
{
    /**
     * Every field is listed. Returning the model's attributes wholesale would
     * publish any column added later, which is how internal fields leak.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->resource->uuid,
            'reference_number' => $this->resource->reference_number,
            'department_id' => $this->resource->department_id,
            'category_id' => $this->resource->category_id,
            'title' => $this->resource->title,
            'description' => $this->resource->description,
            'status' => $this->resource->status->value,
            'priority' => $this->resource->priority->value,
            'source' => $this->resource->source,
            'app_key' => $this->resource->app_key,
            'company_id' => $this->resource->company_id,
            'requester_name' => $this->resource->requester_name,
            'requester_email' => $this->resource->requester_email,
            'closed_at' => $this->resource->closed_at?->toIso8601String(),
            'due_at' => $this->resource->due_at?->toIso8601String(),
            'last_replied_at' => $this->resource->last_replied_at?->toIso8601String(),
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
            'comments' => TicketCommentResource::collection(
                $this->whenLoaded('comments'),
            ),
            'attachments' => TicketAttachmentResource::collection(
                $this->whenLoaded('attachments'),
            ),
        ];
    }
}
