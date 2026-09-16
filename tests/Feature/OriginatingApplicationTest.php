<?php

use JeffersonGoncalves\HelpDesk\Models\Department;
use JeffersonGoncalves\HelpDesk\Models\Ticket;

function ticketFor(?string $appKey, ?string $appName = null): Ticket
{
    config()->set('help-desk.app.key', $appKey);
    config()->set('help-desk.app.name', $appName);

    return Ticket::create([
        'department_id' => Department::factory()->create()->id,
        'user_type' => 'app-user',
        'user_id' => 1,
        'title' => 'Printer offline',
        'description' => 'It stopped printing.',
    ]);
}

// Regression guard for the reset in tests/Pest.php. Pest randomises the order,
// so this lands after a test that set these and fails if they leaked.
it('starts with the package defaults whatever ran before', function () {
    expect(config('help-desk.connection'))->toBeNull()
        ->and(config('help-desk.app.key'))->toBeNull()
        ->and(config('help-desk.app.name'))->toBeNull()
        ->and(config('help-desk.scope_to_app'))->toBeFalse();
});

it('stamps the configured application key on new tickets', function () {
    expect(ticketFor('app-a')->app_key)->toBe('app-a');
});

it('leaves the key null when no application is configured', function () {
    expect(ticketFor(null)->app_key)->toBeNull();
});

it('keeps an explicitly provided key', function () {
    config()->set('help-desk.app.key', 'app-a');

    $ticket = Ticket::create([
        'department_id' => Department::factory()->create()->id,
        'user_type' => 'app-user',
        'user_id' => 1,
        'title' => 'Printer offline',
        'description' => 'It stopped printing.',
        'app_key' => 'imported',
    ]);

    expect($ticket->app_key)->toBe('imported');
});

it('stores the application label so another application can show it', function () {
    $ticket = ticketFor('app-a', 'Application A');

    config()->set('help-desk.app.name', null);

    expect($ticket->fresh()->app_name)->toBe('Application A');
});

it('falls back to the key when no label was stored', function () {
    expect(ticketFor('app-a')->app_name)->toBe('app-a');
});

it('has no application name when the ticket has no key', function () {
    expect(ticketFor(null)->app_name)->toBeNull();
});

it('filters by application with the forApp scope', function () {
    ticketFor('app-a');
    ticketFor('app-b');
    ticketFor(null);

    expect(Ticket::forApp('app-a')->count())->toBe(1)
        ->and(Ticket::forApp('app-b')->count())->toBe(1)
        ->and(Ticket::forApp(null)->count())->toBe(1)
        ->and(Ticket::count())->toBe(3);
});

it('reads every application by default', function () {
    ticketFor('app-a');
    ticketFor('app-b');

    config()->set('help-desk.app.key', null);

    expect(Ticket::count())->toBe(2);
});

it('reads only its own tickets when scoping is enabled', function () {
    ticketFor('app-a');
    ticketFor('app-b');
    ticketFor(null);

    config()->set('help-desk.app.key', 'app-a');
    config()->set('help-desk.scope_to_app', true);

    expect(Ticket::count())->toBe(1)
        ->and(Ticket::first()->app_key)->toBe('app-a');
});

it('does not scope when an application key is set but scoping is off', function () {
    ticketFor('app-a');
    ticketFor('app-b');

    config()->set('help-desk.app.key', 'app-a');
    config()->set('help-desk.scope_to_app', false);

    expect(Ticket::count())->toBe(2);
});

it('keeps the scope unambiguous when joined against another table', function () {
    ticketFor('app-a');

    config()->set('help-desk.app.key', 'app-a');
    config()->set('help-desk.scope_to_app', true);

    // help_desk_ticket_comments has no app_key column, so an unqualified
    // where() in the global scope would resolve fine here but break on a join
    // against a table that does have one.
    expect(Ticket::query()->join(
        'help_desk_ticket_comments',
        'help_desk_ticket_comments.ticket_id',
        '=',
        'help_desk_tickets.id'
    )->count())->toBe(0);
});
