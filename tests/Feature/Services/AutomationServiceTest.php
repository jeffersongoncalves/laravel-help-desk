<?php

use Illuminate\Support\Facades\Event;
use JeffersonGoncalves\HelpDesk\Enums\TicketStatus;
use JeffersonGoncalves\HelpDesk\Events\AutomationRuleTriggered;
use JeffersonGoncalves\HelpDesk\Exceptions\InvalidStatusTransitionException;
use JeffersonGoncalves\HelpDesk\Models\AutomationRule;
use JeffersonGoncalves\HelpDesk\Models\Department;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Services\AutomationService;
use JeffersonGoncalves\HelpDesk\Tests\TestUser;

beforeEach(function () {
    $this->service = app(AutomationService::class);
    $this->user = TestUser::create(['name' => 'Jane Requester', 'email' => 'jane@example.com']);
    $this->department = Department::create(['name' => 'Support', 'slug' => 'support', 'is_active' => true]);
});

function makeAutomationTicket(array $overrides = []): Ticket
{
    return Ticket::create(array_merge([
        'department_id' => test()->department->id,
        'user_type' => test()->user->getMorphClass(),
        'user_id' => test()->user->id,
        'title' => 'Test Ticket',
        'description' => 'Test description',
        'status' => TicketStatus::Pending,
    ], $overrides));
}

function makeAutomationRule(array $overrides = []): AutomationRule
{
    return AutomationRule::create(array_merge([
        'name' => 'Auto-close stale pending tickets',
        'conditions' => [
            'field' => 'last_replied_at',
            'operator' => 'older_than_hours',
            'value' => 24,
            'status' => ['pending'],
        ],
        'actions' => ['type' => 'change_status', 'value' => 'closed'],
        'is_active' => true,
    ], $overrides));
}

it('selects only tickets matching both the field condition and the status condition', function () {
    $matching = makeAutomationTicket(['last_replied_at' => now()->subHours(48)]);
    makeAutomationTicket(['last_replied_at' => now()->subHours(1)]);
    makeAutomationTicket(['last_replied_at' => now()->subHours(48), 'status' => TicketStatus::Open]);

    makeAutomationRule();

    Event::fake([AutomationRuleTriggered::class]);

    $this->service->evaluate();

    Event::assertDispatched(AutomationRuleTriggered::class, fn ($event) => $event->ticket->is($matching));
    Event::assertDispatchedTimes(AutomationRuleTriggered::class, 1);
});

it('applies the change_status action through TicketService, producing the normal event trail', function () {
    $ticket = makeAutomationTicket(['last_replied_at' => now()->subHours(48)]);
    makeAutomationRule();

    $this->service->evaluate();

    expect($ticket->fresh()->status)->toBe(TicketStatus::Closed)
        ->and($ticket->fresh()->closed_at)->not->toBeNull();
});

it('skips a ticket already flagged as applied for a given rule', function () {
    $ticket = makeAutomationTicket(['last_replied_at' => now()->subHours(48)]);
    $rule = makeAutomationRule();

    $this->service->evaluate();

    // Reopen it manually so the condition would match again, and confirm the
    // guard -- not the condition -- is what prevents re-processing. refresh()
    // first, or Eloquent sees 'status' as unchanged from this stale instance's
    // point of view and drops it from the update entirely.
    $ticket->refresh()->update(['status' => TicketStatus::Pending, 'last_replied_at' => now()->subHours(48)]);

    Event::fake([AutomationRuleTriggered::class]);

    $this->service->evaluate();

    Event::assertNotDispatched(AutomationRuleTriggered::class);
    expect($ticket->fresh()->status)->toBe(TicketStatus::Pending);
});

it('rejects an unknown field before querying', function () {
    makeAutomationRule(['conditions' => [
        'field' => 'secret_column',
        'operator' => 'older_than_hours',
        'value' => 24,
    ]]);

    expect(fn () => $this->service->evaluate())
        ->toThrow(InvalidArgumentException::class, 'secret_column');
});

it('rejects an unknown operator before querying', function () {
    makeAutomationRule(['conditions' => [
        'field' => 'last_replied_at',
        'operator' => 'is_haunted',
        'value' => 24,
    ]]);

    expect(fn () => $this->service->evaluate())
        ->toThrow(InvalidArgumentException::class, 'is_haunted');
});

it('does not bypass status transition validation for the change_status action', function () {
    $ticket = makeAutomationTicket([
        'last_replied_at' => now()->subHours(48),
        'status' => TicketStatus::Closed,
    ]);

    makeAutomationRule([
        'conditions' => [
            'field' => 'last_replied_at',
            'operator' => 'older_than_hours',
            'value' => 24,
            'status' => ['closed'],
        ],
        'actions' => ['type' => 'change_status', 'value' => 'in_progress'],
    ]);

    expect(fn () => $this->service->evaluate())->toThrow(InvalidStatusTransitionException::class);
});

it('does not apply actions or write guard rows on a dry run', function () {
    $ticket = makeAutomationTicket(['last_replied_at' => now()->subHours(48)]);
    makeAutomationRule();

    Event::fake([AutomationRuleTriggered::class]);

    $result = $this->service->evaluate(dryRun: true);

    Event::assertNotDispatched(AutomationRuleTriggered::class);
    expect($ticket->fresh()->status)->toBe(TicketStatus::Pending)
        ->and($result)->toHaveCount(1);

    // The dry run's own guard-free evaluate() would re-match the same ticket.
    Event::fake([AutomationRuleTriggered::class]);
    $this->service->evaluate();
    Event::assertDispatchedTimes(AutomationRuleTriggered::class, 1);
});

it('ignores an inactive rule', function () {
    makeAutomationTicket(['last_replied_at' => now()->subHours(48)]);
    makeAutomationRule(['is_active' => false]);

    Event::fake([AutomationRuleTriggered::class]);

    $this->service->evaluate();

    Event::assertNotDispatched(AutomationRuleTriggered::class);
});

it('scopes matching tickets to the rule department when one is set', function () {
    $otherDepartment = Department::create(['name' => 'Sales', 'slug' => 'sales', 'is_active' => true]);
    $outOfScope = makeAutomationTicket(['last_replied_at' => now()->subHours(48), 'department_id' => $otherDepartment->id]);
    $inScope = makeAutomationTicket(['last_replied_at' => now()->subHours(48)]);

    makeAutomationRule(['department_id' => $this->department->id]);

    Event::fake([AutomationRuleTriggered::class]);

    $this->service->evaluate();

    Event::assertDispatched(AutomationRuleTriggered::class, fn ($event) => $event->ticket->is($inScope));
    Event::assertDispatchedTimes(AutomationRuleTriggered::class, 1);
});
