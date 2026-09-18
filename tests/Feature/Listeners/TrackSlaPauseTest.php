<?php

use JeffersonGoncalves\HelpDesk\Enums\TicketStatus;
use JeffersonGoncalves\HelpDesk\Events\TicketStatusChanged;
use JeffersonGoncalves\HelpDesk\Listeners\TrackSlaPause;
use JeffersonGoncalves\HelpDesk\Models\Department;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Tests\TestUser;

it('pauses the ticket when the status change lands on Pending', function () {
    $user = TestUser::create(['name' => 'Jane Requester', 'email' => 'jane@example.com']);
    $department = Department::create(['name' => 'Support', 'slug' => 'support', 'is_active' => true]);

    $ticket = Ticket::create([
        'department_id' => $department->id,
        'user_type' => $user->getMorphClass(),
        'user_id' => $user->id,
        'title' => 'Test Ticket',
        'description' => 'Test description',
        'status' => TicketStatus::Pending,
    ]);

    app(TrackSlaPause::class)->handle(new TicketStatusChanged($ticket, TicketStatus::Open, TicketStatus::Pending));

    expect($ticket->sla_paused_at)->not->toBeNull();
});
