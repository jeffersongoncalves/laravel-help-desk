<?php

use JeffersonGoncalves\HelpDesk\Enums\TicketPriority;
use JeffersonGoncalves\HelpDesk\Enums\TicketStatus;
use JeffersonGoncalves\HelpDesk\Models\Department;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Tests\TestUser;

beforeEach(function () {
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

function createTicket(array $overrides = []): Ticket
{
    return Ticket::create(array_merge([
        'department_id' => test()->department->id,
        'user_type' => test()->user->getMorphClass(),
        'user_id' => test()->user->id,
        'title' => 'Test Ticket',
        'description' => 'Test description',
    ], $overrides));
}

it('auto generates uuid', function () {
    $ticket = createTicket();

    expect($ticket->uuid)->not->toBeEmpty();
});

it('auto generates reference number', function () {
    $ticket = createTicket();

    expect($ticket->reference_number)->toStartWith('HD-');
});

it('defaults to open status', function () {
    $ticket = createTicket();

    expect($ticket->status)->toBe(TicketStatus::Open);
});

it('defaults to medium priority', function () {
    $ticket = createTicket();

    expect($ticket->priority)->toBe(TicketPriority::Medium);
});

it('belongs to a department', function () {
    $ticket = createTicket();

    expect($ticket->department->id)->toBe($this->department->id);
});

it('morphs to user', function () {
    $ticket = createTicket();

    expect($ticket->user->id)->toBe($this->user->id);
});

it('returns true for isOpen on open ticket', function () {
    $ticket = createTicket(['status' => TicketStatus::Open]);

    expect($ticket->isOpen())->toBeTrue()
        ->and($ticket->isClosed())->toBeFalse();
});

it('excludes closed and resolved from open scope', function () {
    createTicket(['status' => TicketStatus::Open]);
    createTicket(['status' => TicketStatus::Closed]);

    expect(Ticket::open()->get())->toHaveCount(1);
});

it('uses uuid as route key name', function () {
    expect((new Ticket)->getRouteKeyName())->toBe('uuid');
});
