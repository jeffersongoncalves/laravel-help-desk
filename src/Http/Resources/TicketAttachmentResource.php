<?php

namespace JeffersonGoncalves\HelpDesk\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JeffersonGoncalves\HelpDesk\Models\TicketAttachment;

/**
 * @property TicketAttachment $resource
 */
class TicketAttachmentResource extends JsonResource
{
    /**
     * No URL. The satellite has no access to the disk the file sits on, so a
     * URL here would be one that 404s for its users — worse than admitting
     * there is none. The download endpoint serves the bytes instead.
     *
     * `file_path` and `disk` are likewise absent: they describe the central
     * application's storage and are no one else's business.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->resource->uuid,
            'comment_id' => $this->resource->comment_id,
            'file_name' => $this->resource->file_name,
            'mime_type' => $this->resource->mime_type,
            'file_size' => $this->resource->file_size,
            'uploader_name' => $this->resource->uploader_name,
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
