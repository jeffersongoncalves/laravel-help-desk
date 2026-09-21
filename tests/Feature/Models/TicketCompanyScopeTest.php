<?php

use JeffersonGoncalves\HelpDesk\Models\Department;
use JeffersonGoncalves\HelpDesk\Models\Ticket;

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

it('filters by company with the forCompany scope', function () {
    ticketForCompany('acme-inc');
    ticketForCompany('other-co');
    ticketForCompany(null);

    expect(Ticket::forCompany('acme-inc')->count())->toBe(1)
        ->and(Ticket::forCompany('other-co')->count())->toBe(1)
        ->and(Ticket::forCompany(null)->count())->toBe(1)
        ->and(Ticket::count())->toBe(3);
});
