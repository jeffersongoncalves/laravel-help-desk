<?php

return [
    'department' => 'Department',
    'departments' => 'Departments',
    'create' => 'Create Department',
    'edit' => 'Edit Department',
    'delete' => 'Delete Department',

    'fields' => [
        'name' => 'Name',
        'slug' => 'Slug',
        'description' => 'Description',
        'email' => 'Email',
        'is_active' => 'Active',
        'sort_order' => 'Sort Order',
    ],

    'messages' => [
        'created' => 'Department created successfully.',
        'updated' => 'Department updated successfully.',
        'deleted' => 'Department deleted successfully.',
        'not_found' => 'Department not found.',
        'has_tickets' => 'Cannot delete department that has tickets.',
    ],

    'roles' => [
        'operator' => 'Operator',
        'manager' => 'Manager',
        'admin' => 'Admin',
    ],
];
