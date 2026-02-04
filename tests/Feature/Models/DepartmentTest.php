<?php

namespace JeffersonGoncalves\HelpDesk\Tests\Feature\Models;

use JeffersonGoncalves\HelpDesk\Models\Category;
use JeffersonGoncalves\HelpDesk\Models\Department;
use JeffersonGoncalves\HelpDesk\Tests\TestCase;

class DepartmentTest extends TestCase
{
    public function test_create_department(): void
    {
        $department = Department::create([
            'name' => 'Technical Support',
            'slug' => 'technical-support',
            'description' => 'Technical support department',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('help_desk_departments', [
            'name' => 'Technical Support',
            'slug' => 'technical-support',
        ]);
    }

    public function test_department_has_categories(): void
    {
        $department = Department::create([
            'name' => 'Support',
            'slug' => 'support',
            'is_active' => true,
        ]);

        Category::create([
            'department_id' => $department->id,
            'name' => 'Billing',
            'slug' => 'billing',
            'is_active' => true,
        ]);

        $this->assertCount(1, $department->fresh()->categories);
    }

    public function test_active_scope(): void
    {
        Department::create([
            'name' => 'Active',
            'slug' => 'active',
            'is_active' => true,
        ]);

        Department::create([
            'name' => 'Inactive',
            'slug' => 'inactive',
            'is_active' => false,
        ]);

        $this->assertCount(1, Department::active()->get());
    }

    public function test_soft_delete(): void
    {
        $department = Department::create([
            'name' => 'To Delete',
            'slug' => 'to-delete',
            'is_active' => true,
        ]);

        $department->delete();

        $this->assertSoftDeleted('help_desk_departments', ['id' => $department->id]);
        $this->assertCount(0, Department::all());
        $this->assertCount(1, Department::withTrashed()->get());
    }
}
