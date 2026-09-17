<?php

namespace JeffersonGoncalves\HelpDesk\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JeffersonGoncalves\HelpDesk\Models\Category;

/**
 * @property Category $resource
 */
class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'department_id' => $this->resource->department_id,
            'parent_id' => $this->resource->parent_id,
            'name' => $this->resource->name,
            'slug' => $this->resource->slug,
        ];
    }
}
