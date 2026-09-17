<?php

use JeffersonGoncalves\HelpDesk\Enums\TicketPriority;
use JeffersonGoncalves\HelpDesk\Events\TicketCreated;
use JeffersonGoncalves\HelpDesk\Listeners\ApplySlaPolicy;
use JeffersonGoncalves\HelpDesk\Models\Department;
use JeffersonGoncalves\HelpDesk\Models\SlaPolicy;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Tests\TestUser;

it('applies a matching policy to a newly created ticket', function () {
    $user = TestUser::create(['name' => 'Jane Requester', 'email' => 'jane@example.com']);
    $department = Department::create(['name' => 'Support', 'slug' => 'support', 'is_active' => true]);

    $policy = SlaPolicy::create([
        'department_id' => $department->id,
        'first_response_minutes' => 60,
        'resolution_minutes' => 1440,
        'is_active' => true,
    ]);

    $ticket = Ticket::create([
        'department_id' => $department->id,
        'user_type' => $user->getMorphClass(),
        'user_id' => $user->id,
        'title' => 'Test Ticket',
        'description' => 'Test description',
        'priority' => TicketPriority::Medium,
    ]);

    app(ApplySlaPolicy::class)->handle(new TicketCreated($ticket));

    expect($ticket->sla_policy_id)->toBe($policy->id)
        ->and($ticket->sla_first_response_due_at)->not->toBeNull();
});
