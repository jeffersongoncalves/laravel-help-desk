<?php

use JeffersonGoncalves\HelpDesk\Facades\HelpDesk;
use JeffersonGoncalves\HelpDesk\Models\Department;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Tests\TestUser;

function ticketForCompany(?string $companyId): Ticket
{
    return Ticket::create([
        'department_id' => Department::factory()->create()->id,
        'user_type' => 'app-user',
        'user_id' => 1,
        'title' => 'Printer offline',
        'description' => 'It stopped printing.',
        'company_id' => $companyId,
    ]);
}

it('stores the company id passed in on creation', function () {
    expect(ticketForCompany('acme-inc')->company_id)->toBe('acme-inc');
});

it('leaves the company id null when none is given', function () {
    expect(ticketForCompany(null)->company_id)->toBeNull();
});

it('snapshots the company name passed to createTicket', function () {
    $ticket = HelpDesk::createTicket([
        'department_id' => Department::factory()->create()->id,
        'title' => 'Printer offline',
        'description' => 'It stopped printing.',
        'company_id' => '4',
        'company_name' => 'Acme Inc.',
    ], TestUser::create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']));

    expect($ticket->fresh()->company_name)->toBe('Acme Inc.')
        ->and($ticket->fresh()->metadata['company'])->toBe(['name' => 'Acme Inc.']);
});

it('falls back to the company id when no name was stored', function () {
    expect(ticketForCompany('4')->company_name)->toBe('4')
        ->and(ticketForCompany(null)->company_name)->toBeNull();
});

it('filters by company with the forCompany scope', function () {
    ticketForCompany('acme-inc');
    ticketForCompany('other-co');
    ticketForCompany(null);

    expect(Ticket::forCompany('acme-inc')->count())->toBe(1)
        ->and(Ticket::forCompany('other-co')->count())->toBe(1)
        ->and(Ticket::forCompany(null)->count())->toBe(1)
        ->and(Ticket::count())->toBe(3);
});
