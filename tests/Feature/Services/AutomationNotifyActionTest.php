<?php

use Illuminate\Support\Facades\Notification;
use JeffersonGoncalves\HelpDesk\Enums\TicketStatus;
use JeffersonGoncalves\HelpDesk\Models\AutomationRule;
use JeffersonGoncalves\HelpDesk\Models\Department;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Notifications\TicketAutomationTriggeredNotification;
use JeffersonGoncalves\HelpDesk\Services\AutomationService;
use JeffersonGoncalves\HelpDesk\Services\DepartmentService;
use JeffersonGoncalves\HelpDesk\Tests\TestUser;

beforeEach(function () {
    $this->service = app(AutomationService::class);
    $this->requester = TestUser::create(['name' => 'Jane Requester', 'email' => 'jane@example.com']);
    $this->department = Department::create(['name' => 'Support', 'slug' => 'support', 'is_active' => true]);
});

function ticketForNotifyAction(array $overrides = []): Ticket
{
    return Ticket::create(array_merge([
        'department_id' => test()->department->id,
        'user_type' => test()->requester->getMorphClass(),
        'user_id' => test()->requester->id,
        'title' => 'Test Ticket',
        'description' => 'Test description',
        'status' => TicketStatus::Pending,
        'last_replied_at' => now()->subHours(48),
    ], $overrides));
}

function ruleForNotifyAction(array $overrides = []): AutomationRule
{
    return AutomationRule::create(array_merge([
        'name' => 'Notify on stale pending tickets',
        'conditions' => [
            'field' => 'last_replied_at',
            'operator' => 'older_than_hours',
            'value' => 24,
            'status' => ['pending'],
        ],
        'actions' => ['type' => 'notify', 'notifiable' => 'assigned_to'],
        'is_active' => true,
    ], $overrides));
}

it('notifies the assigned operator', function () {
    $operator = TestUser::create(['name' => 'Ops', 'email' => 'ops@example.com']);
    $ticket = ticketForNotifyAction(['assigned_to_type' => $operator->getMorphClass(), 'assigned_to_id' => $operator->id]);
    ruleForNotifyAction();

    Notification::fake();

    $this->service->evaluate();

    Notification::assertSentTo($operator, TicketAutomationTriggeredNotification::class);
});

it('is a no-op, not an exception, when assigned_to has nobody assigned, but still writes the guard row', function () {
    $ticket = ticketForNotifyAction();
    $rule = ruleForNotifyAction();

    Notification::fake();

    $this->service->evaluate();

    Notification::assertNothingSent();

    // Guard row written even though nothing was notified -- otherwise this
    // ticket+rule pair would be re-evaluated (and re-skipped) on every run.
    $ticket->refresh()->update(['last_replied_at' => now()->subHours(48)]);
    $this->service->evaluate();
    Notification::assertNothingSent();
});

it('notifies every operator in the ticket department', function () {
    $departmentService = app(DepartmentService::class);
    $operatorA = TestUser::create(['name' => 'Ops A', 'email' => 'a@example.com']);
    $operatorB = TestUser::create(['name' => 'Ops B', 'email' => 'b@example.com']);
    $departmentService->addOperator($this->department, $operatorA);
    $departmentService->addOperator($this->department, $operatorB);

    ticketForNotifyAction();
    ruleForNotifyAction(['actions' => ['type' => 'notify', 'notifiable' => 'department_operators']]);

    Notification::fake();

    $this->service->evaluate();

    Notification::assertSentTo($operatorA, TicketAutomationTriggeredNotification::class);
    Notification::assertSentTo($operatorB, TicketAutomationTriggeredNotification::class);
});

it('notifies the requester', function () {
    ticketForNotifyAction();
    ruleForNotifyAction(['actions' => ['type' => 'notify', 'notifiable' => 'requester']]);

    Notification::fake();

    $this->service->evaluate();

    Notification::assertSentTo($this->requester, TicketAutomationTriggeredNotification::class);
});

it('does not notify twice across two command runs', function () {
    $operator = TestUser::create(['name' => 'Ops', 'email' => 'ops@example.com']);
    $ticket = ticketForNotifyAction(['assigned_to_type' => $operator->getMorphClass(), 'assigned_to_id' => $operator->id]);
    ruleForNotifyAction();

    Notification::fake();

    $this->service->evaluate();
    Notification::assertSentTo($operator, TicketAutomationTriggeredNotification::class, 1);

    $ticket->refresh()->update(['last_replied_at' => now()->subHours(48)]);
    Notification::fake();

    $this->service->evaluate();
    Notification::assertNothingSent();
});

it('applies change_status and notify together, in order, in one evaluation pass', function () {
    $operator = TestUser::create(['name' => 'Ops', 'email' => 'ops@example.com']);
    $ticket = ticketForNotifyAction(['assigned_to_type' => $operator->getMorphClass(), 'assigned_to_id' => $operator->id]);
    ruleForNotifyAction(['actions' => [
        ['type' => 'change_status', 'value' => 'closed'],
        ['type' => 'notify', 'notifiable' => 'assigned_to'],
    ]]);

    Notification::fake();

    $this->service->evaluate();

    expect($ticket->fresh()->status)->toBe(TicketStatus::Closed);
    Notification::assertSentTo($operator, TicketAutomationTriggeredNotification::class);
});
