<?php

use Illuminate\Support\Facades\Event;
use JeffersonGoncalves\HelpDesk\Enums\TicketStatus;
use JeffersonGoncalves\HelpDesk\Events\TicketAssigned;
use JeffersonGoncalves\HelpDesk\Events\TicketClosed;
use JeffersonGoncalves\HelpDesk\Events\TicketCreated;
use JeffersonGoncalves\HelpDesk\Events\TicketReopened;
use JeffersonGoncalves\HelpDesk\Events\TicketStatusChanged;
use JeffersonGoncalves\HelpDesk\Events\TicketUpdated;
use JeffersonGoncalves\HelpDesk\Exceptions\InvalidStatusTransitionException;
use JeffersonGoncalves\HelpDesk\Exceptions\TicketNotFoundException;
use JeffersonGoncalves\HelpDesk\Models\Department;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Services\TicketService;
use JeffersonGoncalves\HelpDesk\Tests\TestUser;

beforeEach(function () {
    $this->service = app(TicketService::class);

    $this->user = TestUser::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);

    $this->department = Department::create([
        'name' => 'Support',
        'slug' => 'support',
        'is_active' => true,
    ]);
});

function createServiceTicket(array $overrides = []): Ticket
{
    return Ticket::create(array_merge([
        'department_id' => test()->department->id,
        'user_type' => test()->user->getMorphClass(),
        'user_id' => test()->user->id,
        'title' => 'Test Ticket',
        'description' => 'Test description',
    ], $overrides));
}

it('creates a ticket', function () {
    Event::fake([TicketCreated::class]);

    $ticket = $this->service->create([
        'title' => 'Test Ticket',
        'description' => 'Test description',
        'department_id' => $this->department->id,
    ], $this->user);

    expect($ticket)
        ->toBeInstanceOf(Ticket::class)
        ->title->toBe('Test Ticket')
        ->user_id->toBe($this->user->id)
        ->uuid->not->toBeEmpty()
        ->reference_number->not->toBeEmpty();

    Event::assertDispatched(TicketCreated::class);
});

it('changes ticket status', function () {
    $ticket = createServiceTicket();

    Event::fake([TicketStatusChanged::class, TicketUpdated::class]);

    $updated = $this->service->changeStatus($ticket, TicketStatus::InProgress);

    expect($updated->status)->toBe(TicketStatus::InProgress);

    Event::assertDispatched(TicketStatusChanged::class);
});

it('throws exception on invalid status transition', function () {
    $ticket = createServiceTicket(['status' => TicketStatus::Closed]);

    $this->service->changeStatus($ticket, TicketStatus::InProgress);
})->throws(InvalidStatusTransitionException::class);

it('throws exception on invalid status transition via update()', function () {
    $ticket = createServiceTicket(['status' => TicketStatus::Closed]);

    $this->service->update($ticket, ['status' => TicketStatus::InProgress]);
})->throws(InvalidStatusTransitionException::class);

it('does not fire status events twice when update() changes status', function () {
    $ticket = createServiceTicket();

    Event::fake([TicketStatusChanged::class, TicketUpdated::class]);

    $updated = $this->service->update($ticket, ['status' => TicketStatus::InProgress]);

    expect($updated->status)->toBe(TicketStatus::InProgress);

    Event::assertDispatchedTimes(TicketStatusChanged::class, 1);
    Event::assertDispatchedTimes(TicketUpdated::class, 1);
});

it('updates status alongside other fields via update()', function () {
    $ticket = createServiceTicket();

    Event::fake([TicketStatusChanged::class, TicketUpdated::class]);

    $updated = $this->service->update($ticket, [
        'status' => TicketStatus::InProgress,
        'title' => 'Renamed ticket',
    ]);

    expect($updated->status)->toBe(TicketStatus::InProgress)
        ->and($updated->title)->toBe('Renamed ticket');

    Event::assertDispatched(TicketUpdated::class, function (TicketUpdated $event) {
        return array_key_exists('status', $event->changes) && array_key_exists('title', $event->changes);
    });
});

it('allows update() to accept a status value equal to the current status', function () {
    $ticket = createServiceTicket();

    $updated = $this->service->update($ticket, ['status' => $ticket->status]);

    expect($updated->status)->toBe($ticket->status);
});

it('closes a ticket', function () {
    $ticket = createServiceTicket();

    Event::fake([TicketClosed::class, TicketStatusChanged::class, TicketUpdated::class]);

    $closed = $this->service->close($ticket);

    expect($closed->status)->toBe(TicketStatus::Closed);

    Event::assertDispatched(TicketClosed::class);
});

it('reopens a ticket', function () {
    $ticket = createServiceTicket(['status' => TicketStatus::Closed]);

    Event::fake([TicketReopened::class, TicketStatusChanged::class, TicketUpdated::class]);

    $reopened = $this->service->reopen($ticket);

    expect($reopened->status)->toBe(TicketStatus::Open);

    Event::assertDispatched(TicketReopened::class);
});

it('assigns a ticket to an operator', function () {
    $operator = TestUser::create([
        'name' => 'Operator',
        'email' => 'operator@example.com',
    ]);

    $assignedBy = TestUser::create([
        'name' => 'Manager',
        'email' => 'manager@example.com',
    ]);

    $ticket = createServiceTicket();

    Event::fake([TicketAssigned::class]);

    $assigned = $this->service->assign($ticket, $operator, $assignedBy);

    expect($assigned->assigned_to_id)->toBe($operator->id);

    Event::assertDispatched(TicketAssigned::class, function (TicketAssigned $event) use ($operator, $assignedBy) {
        return $event->assignedTo->is($operator) && $event->assignedBy->is($assignedBy);
    });
});

it('unassigns a ticket and carries the performer on the event', function () {
    $operator = TestUser::create([
        'name' => 'Operator',
        'email' => 'operator@example.com',
    ]);

    $performer = TestUser::create([
        'name' => 'Manager',
        'email' => 'manager@example.com',
    ]);

    $ticket = createServiceTicket();
    $this->service->assign($ticket, $operator);

    Event::fake([TicketUpdated::class]);

    $unassigned = $this->service->unassign($ticket, $performer);

    expect($unassigned->assigned_to_id)->toBeNull();

    Event::assertDispatched(TicketUpdated::class, function (TicketUpdated $event) use ($performer) {
        return $event->performer !== null && $event->performer->is($performer);
    });
});

it('finds a ticket by uuid', function () {
    $ticket = createServiceTicket();

    $found = $this->service->findByUuid($ticket->uuid);

    expect($found->id)->toBe($ticket->id);
});

it('throws exception when ticket not found by uuid', function () {
    $this->service->findByUuid('nonexistent-uuid');
})->throws(TicketNotFoundException::class);

it('finds a ticket by reference number', function () {
    $ticket = createServiceTicket();

    $found = $this->service->findByReference($ticket->reference_number);

    expect($found->id)->toBe($ticket->id);
});

it('adds and removes a watcher', function () {
    $ticket = createServiceTicket();

    $watcher = TestUser::create([
        'name' => 'Watcher',
        'email' => 'watcher@example.com',
    ]);

    $this->service->addWatcher($ticket, $watcher);
    expect($ticket->fresh()->watchers)->toHaveCount(1);

    $this->service->removeWatcher($ticket, $watcher);
    expect($ticket->fresh()->watchers)->toHaveCount(0);
});
