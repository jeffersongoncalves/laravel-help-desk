<?php

use Illuminate\Support\Facades\Event;
use JeffersonGoncalves\HelpDesk\Enums\TicketStatus;
use JeffersonGoncalves\HelpDesk\Events\TicketSlaFirstResponseBreached;
use JeffersonGoncalves\HelpDesk\Events\TicketSlaResolutionBreached;
use JeffersonGoncalves\HelpDesk\Models\Department;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Tests\TestUser;

beforeEach(function () {
    $this->user = TestUser::create(['name' => 'Jane Requester', 'email' => 'jane@example.com']);
    $this->department = Department::create(['name' => 'Support', 'slug' => 'support', 'is_active' => true]);
});

function makeBreachTicket(array $overrides = []): Ticket
{
    return Ticket::create(array_merge([
        'department_id' => test()->department->id,
        'user_type' => test()->user->getMorphClass(),
        'user_id' => test()->user->id,
        'title' => 'Test Ticket',
        'description' => 'Test description',
    ], $overrides));
}

it('flags and dispatches a first-response breach', function () {
    $ticket = makeBreachTicket(['sla_first_response_due_at' => now()->subHour()]);

    Event::fake([TicketSlaFirstResponseBreached::class]);

    $this->artisan('help-desk:check-sla-breaches')->assertExitCode(0);

    Event::assertDispatched(TicketSlaFirstResponseBreached::class, fn ($event) => $event->ticket->is($ticket));
    expect($ticket->fresh()->sla_first_response_breached_at)->not->toBeNull();
});

it('does not flag a first-response breach twice', function () {
    makeBreachTicket(['sla_first_response_due_at' => now()->subHour()]);

    $this->artisan('help-desk:check-sla-breaches')->assertExitCode(0);

    Event::fake([TicketSlaFirstResponseBreached::class]);

    $this->artisan('help-desk:check-sla-breaches')->assertExitCode(0);

    Event::assertNotDispatched(TicketSlaFirstResponseBreached::class);
});

it('does not flag a first-response breach once a response was recorded', function () {
    makeBreachTicket([
        'sla_first_response_due_at' => now()->subHour(),
        'first_response_at' => now()->subMinutes(30),
    ]);

    Event::fake([TicketSlaFirstResponseBreached::class]);

    $this->artisan('help-desk:check-sla-breaches')->assertExitCode(0);

    Event::assertNotDispatched(TicketSlaFirstResponseBreached::class);
});

it('flags and dispatches a resolution breach', function () {
    $ticket = makeBreachTicket(['sla_resolution_due_at' => now()->subHour()]);

    Event::fake([TicketSlaResolutionBreached::class]);

    $this->artisan('help-desk:check-sla-breaches')->assertExitCode(0);

    Event::assertDispatched(TicketSlaResolutionBreached::class, fn ($event) => $event->ticket->is($ticket));
    expect($ticket->fresh()->sla_resolution_breached_at)->not->toBeNull();
});

it('does not flag a resolution breach twice', function () {
    makeBreachTicket(['sla_resolution_due_at' => now()->subHour()]);

    $this->artisan('help-desk:check-sla-breaches')->assertExitCode(0);

    Event::fake([TicketSlaResolutionBreached::class]);

    $this->artisan('help-desk:check-sla-breaches')->assertExitCode(0);

    Event::assertNotDispatched(TicketSlaResolutionBreached::class);
});

it('does not flag a resolution breach while the ticket is paused', function () {
    makeBreachTicket([
        'sla_resolution_due_at' => now()->subHour(),
        'sla_paused_at' => now()->subMinutes(30),
    ]);

    Event::fake([TicketSlaResolutionBreached::class]);

    $this->artisan('help-desk:check-sla-breaches')->assertExitCode(0);

    Event::assertNotDispatched(TicketSlaResolutionBreached::class);
});

it('does not flag a closed ticket', function () {
    makeBreachTicket([
        'status' => TicketStatus::Closed,
        'sla_first_response_due_at' => now()->subHour(),
        'sla_resolution_due_at' => now()->subHour(),
    ]);

    Event::fake([TicketSlaFirstResponseBreached::class, TicketSlaResolutionBreached::class]);

    $this->artisan('help-desk:check-sla-breaches')->assertExitCode(0);

    Event::assertNotDispatched(TicketSlaFirstResponseBreached::class);
    Event::assertNotDispatched(TicketSlaResolutionBreached::class);
});

it('does not dispatch events or write breach columns on a dry run', function () {
    $ticket = makeBreachTicket([
        'sla_first_response_due_at' => now()->subHour(),
        'sla_resolution_due_at' => now()->subHour(),
    ]);

    Event::fake([TicketSlaFirstResponseBreached::class, TicketSlaResolutionBreached::class]);

    $this->artisan('help-desk:check-sla-breaches', ['--dry-run' => true])->assertExitCode(0);

    Event::assertNotDispatched(TicketSlaFirstResponseBreached::class);
    Event::assertNotDispatched(TicketSlaResolutionBreached::class);

    $fresh = $ticket->fresh();
    expect($fresh->sla_first_response_breached_at)->toBeNull()
        ->and($fresh->sla_resolution_breached_at)->toBeNull();
});
