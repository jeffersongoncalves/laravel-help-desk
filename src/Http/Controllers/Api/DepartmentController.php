<?php

namespace JeffersonGoncalves\HelpDesk\Http\Controllers\Api;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use JeffersonGoncalves\HelpDesk\Http\Resources\CategoryResource;
use JeffersonGoncalves\HelpDesk\Http\Resources\DepartmentResource;
use JeffersonGoncalves\HelpDesk\Models\Category;
use JeffersonGoncalves\HelpDesk\Models\Department;

/**
 * Departments and categories are not scoped to the caller: they are the
 * options a create form offers, and the central application decides which of
 * them are open for business by marking them active.
 */
class DepartmentController
{
    public function index(): AnonymousResourceCollection
    {
        return DepartmentResource::collection(
            Department::query()->where('is_active', true)->orderBy('sort_order')->get(),
        );
    }

    public function categories(int $department): AnonymousResourceCollection
    {
        return CategoryResource::collection(
            Category::query()
                ->where('department_id', $department)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(),
        );
    }
}
