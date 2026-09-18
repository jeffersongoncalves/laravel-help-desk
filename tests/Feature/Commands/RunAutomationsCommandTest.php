<?php

use Illuminate\Support\Facades\Event;
use JeffersonGoncalves\HelpDesk\Enums\TicketStatus;
use JeffersonGoncalves\HelpDesk\Events\AutomationRuleTriggered;
use JeffersonGoncalves\HelpDesk\Models\AutomationRule;
use JeffersonGoncalves\HelpDesk\Models\Department;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Tests\TestUser;

beforeEach(function () {
    $this->user = TestUser::create(['name' => 'Jane Requester', 'email' => 'jane@example.com']);
    $this->department = Department::create(['name' => 'Support', 'slug' => 'support', 'is_active' => true]);
});

function ticketForRunAutomationsCommand(array $overrides = []): Ticket
{
    return Ticket::create(array_merge([
        'department_id' => test()->department->id,
        'user_type' => test()->user->getMorphClass(),
        'user_id' => test()->user->id,
        'title' => 'Test Ticket',
        'description' => 'Test description',
        'status' => TicketStatus::Pending,
        'last_replied_at' => now()->subHours(48),
    ], $overrides));
}

function ruleForRunAutomationsCommand(): AutomationRule
{
    return AutomationRule::create([
        'name' => 'Auto-close stale pending tickets',
        'conditions' => [
            'field' => 'last_replied_at',
            'operator' => 'older_than_hours',
            'value' => 24,
            'status' => ['pending'],
        ],
        'actions' => ['type' => 'change_status', 'value' => 'closed'],
        'is_active' => true,
    ]);
}

it('applies matching rules and dispatches events', function () {
    $ticket = ticketForRunAutomationsCommand();
    ruleForRunAutomationsCommand();

    Event::fake([AutomationRuleTriggered::class]);

    $this->artisan('help-desk:run-automations')->assertExitCode(0);

    Event::assertDispatchedTimes(AutomationRuleTriggered::class, 1);
    expect($ticket->fresh()->status)->toBe(TicketStatus::Closed);
});

it('does not apply actions on a dry run', function () {
    $ticket = ticketForRunAutomationsCommand();
    ruleForRunAutomationsCommand();

    Event::fake([AutomationRuleTriggered::class]);

    $this->artisan('help-desk:run-automations', ['--dry-run' => true])->assertExitCode(0);

    Event::assertNotDispatched(AutomationRuleTriggered::class);
    expect($ticket->fresh()->status)->toBe(TicketStatus::Pending);
});
