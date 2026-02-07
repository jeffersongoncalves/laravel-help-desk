<?php

return [
    'ticket' => 'Ticket',
    'tickets' => 'Tickets',
    'create' => 'Create Ticket',
    'edit' => 'Edit Ticket',
    'delete' => 'Delete Ticket',
    'close' => 'Close Ticket',
    'reopen' => 'Reopen Ticket',
    'assign' => 'Assign Ticket',
    'unassign' => 'Unassign Ticket',

    'fields' => [
        'title' => 'Title',
        'description' => 'Description',
        'status' => 'Status',
        'priority' => 'Priority',
        'department' => 'Department',
        'category' => 'Category',
        'assigned_to' => 'Assigned To',
        'created_by' => 'Created By',
        'reference_number' => 'Reference Number',
        'source' => 'Source',
        'due_at' => 'Due Date',
        'closed_at' => 'Closed At',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At',
    ],

    'sources' => [
        'web' => 'Web',
        'email' => 'Email',
        'api' => 'API',
    ],

    'validation' => [
        'title_required' => 'The ticket title is required.',
        'title_max' => 'The ticket title must not exceed :max characters.',
        'description_required' => 'The ticket description is required.',
        'department_required' => 'Please select a department.',
        'department_invalid' => 'The selected department is invalid.',
        'category_invalid' => 'The selected category is invalid.',
        'priority_invalid' => 'The selected priority is invalid.',
        'status_invalid' => 'The selected status is invalid.',
        'status_transition_invalid' => 'Cannot transition from :from to :to.',
        'attachment_invalid_type' => 'The file type :type is not allowed.',
        'attachment_too_large' => 'The file must not exceed :max KB.',
        'attachment_limit_exceeded' => 'You can attach a maximum of :max files per comment.',
    ],

    'messages' => [
        'created' => 'Ticket created successfully.',
        'updated' => 'Ticket updated successfully.',
        'deleted' => 'Ticket deleted successfully.',
        'closed' => 'Ticket closed successfully.',
        'reopened' => 'Ticket reopened successfully.',
        'assigned' => 'Ticket assigned successfully.',
        'unassigned' => 'Ticket unassigned successfully.',
        'not_found' => 'Ticket not found.',
    ],
];
