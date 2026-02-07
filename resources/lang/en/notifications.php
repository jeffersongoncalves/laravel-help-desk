<?php

return [
    'ticket_created' => [
        'subject' => 'New Ticket Created: :title',
        'greeting' => 'Hello!',
        'body' => 'A new support ticket has been created.',
        'reference' => 'Reference: :reference',
        'department' => 'Department: :department',
        'priority' => 'Priority: :priority',
        'action' => 'View Ticket',
    ],

    'ticket_status_changed' => [
        'subject' => 'Ticket :reference Status Updated',
        'greeting' => 'Hello!',
        'body' => 'The status of your ticket has been updated.',
        'from' => 'Previous Status: :from',
        'to' => 'New Status: :to',
        'action' => 'View Ticket',
    ],

    'ticket_assigned' => [
        'subject' => 'Ticket :reference Assigned to You',
        'greeting' => 'Hello!',
        'body' => 'A support ticket has been assigned to you.',
        'title' => 'Title: :title',
        'priority' => 'Priority: :priority',
        'action' => 'View Ticket',
    ],

    'comment_added' => [
        'subject' => 'New Comment on Ticket :reference',
        'greeting' => 'Hello!',
        'body' => 'A new comment has been added to your ticket.',
        'author' => 'Comment by: :author',
        'action' => 'View Ticket',
    ],

    'ticket_closed' => [
        'subject' => 'Ticket :reference Closed',
        'greeting' => 'Hello!',
        'body' => 'Your support ticket has been closed.',
        'title' => 'Title: :title',
        'action' => 'View Ticket',
    ],
];
