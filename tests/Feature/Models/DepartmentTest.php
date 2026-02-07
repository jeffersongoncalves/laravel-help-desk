<?php

use JeffersonGoncalves\HelpDesk\Models\Category;
use JeffersonGoncalves\HelpDesk\Models\Department;

it('can create a department', function () {
    Department::create([
        'name' => 'Technical Support',
        'slug' => 'technical-support',
        'description' => 'Technical support department',
        'is_active' => true,
    ]);

    $this->assertDatabaseHas('help_desk_departments', [
        'name' => 'Technical Support',
        'slug' => 'technical-support',
    ]);
});

it('has categories', function () {
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

    expect($department->fresh()->categories)->toHaveCount(1);
});

it('filters by active scope', function () {
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

    expect(Department::active()->get())->toHaveCount(1);
});

it('supports soft delete', function () {
    $department = Department::create([
        'name' => 'To Delete',
        'slug' => 'to-delete',
        'is_active' => true,
    ]);

    $department->delete();

    $this->assertSoftDeleted('help_desk_departments', ['id' => $department->id]);

    expect(Department::all())->toHaveCount(0)
        ->and(Department::withTrashed()->get())->toHaveCount(1);
});
