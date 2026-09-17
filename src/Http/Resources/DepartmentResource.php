<?php

namespace JeffersonGoncalves\HelpDesk\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JeffersonGoncalves\HelpDesk\Models\Department;

/**
 * @property Department $resource
 */
class DepartmentResource extends JsonResource
{
    /**
     * Enough to populate a create form, and no more. The department's email
     * address and its operators are not the satellite's business.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'slug' => $this->resource->slug,
        ];
    }
}
