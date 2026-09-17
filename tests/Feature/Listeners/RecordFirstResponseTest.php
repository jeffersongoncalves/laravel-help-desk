<?php

use JeffersonGoncalves\HelpDesk\Enums\CommentType;
use JeffersonGoncalves\HelpDesk\Events\CommentAdded;
use JeffersonGoncalves\HelpDesk\Listeners\RecordFirstResponse;
use JeffersonGoncalves\HelpDesk\Models\Department;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Tests\TestUser;

it('records the first response when a comment is added', function () {
    $user = TestUser::create(['name' => 'Jane Requester', 'email' => 'jane@example.com']);
    $operator = TestUser::create(['name' => 'Agent Smith', 'email' => 'agent@example.com']);
    $department = Department::create(['name' => 'Support', 'slug' => 'support', 'is_active' => true]);

    $ticket = Ticket::create([
        'department_id' => $department->id,
        'user_type' => $user->getMorphClass(),
        'user_id' => $user->id,
        'title' => 'Test Ticket',
        'description' => 'Test description',
    ]);

    $comment = $ticket->comments()->create([
        'author_type' => $operator->getMorphClass(),
        'author_id' => $operator->id,
        'body' => 'How can I help?',
        'type' => CommentType::Reply,
    ]);

    app(RecordFirstResponse::class)->handle(new CommentAdded($ticket, $comment));

    expect($ticket->first_response_at)->not->toBeNull();
});
