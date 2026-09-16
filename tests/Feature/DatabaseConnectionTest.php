<?php

use JeffersonGoncalves\HelpDesk\Database\HelpDeskMigration;
use JeffersonGoncalves\HelpDesk\Models\CannedResponse;
use JeffersonGoncalves\HelpDesk\Models\Category;
use JeffersonGoncalves\HelpDesk\Models\Department;
use JeffersonGoncalves\HelpDesk\Models\EmailChannel;
use JeffersonGoncalves\HelpDesk\Models\InboundEmail;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Models\TicketAttachment;
use JeffersonGoncalves\HelpDesk\Models\TicketComment;
use JeffersonGoncalves\HelpDesk\Models\TicketHistory;
use JeffersonGoncalves\HelpDesk\Models\TicketWatcher;

$models = [
    CannedResponse::class,
    Category::class,
    Department::class,
    EmailChannel::class,
    InboundEmail::class,
    Ticket::class,
    TicketAttachment::class,
    TicketComment::class,
    TicketHistory::class,
    TicketWatcher::class,
];

$originalCentralConnection = null;

beforeEach(function () use (&$originalCentralConnection) {
    // A real connection, so nothing that resolves it by name can fail. Testbench
    // reuses the application between some tests, and a leaked connection name
    // would surface there as the next test's migrations blowing up.
    $originalCentralConnection = config('database.connections.help_desk_central');

    config()->set('database.connections.help_desk_central', config('database.connections.testing'));
});

// Put back whatever was there, so this file cannot define a connection for the
// rest of the suite. Pest runs this even when the test itself throws, and
// tests/Pest.php resets help-desk.connection for every test.
afterEach(function () use (&$originalCentralConnection) {
    config()->set('database.connections.help_desk_central', $originalCentralConnection);
});

it('uses the default connection when none is configured', function (string $model) {
    config()->set('help-desk.connection', null);

    expect((new $model)->getConnectionName())->toBeNull();
})->with($models);

it('uses the configured connection', function (string $model) {
    config()->set('help-desk.connection', 'help_desk_central');

    expect((new $model)->getConnectionName())->toBe('help_desk_central');
})->with($models);

it('lets an explicit setConnection win over the configured one', function () {
    config()->set('help-desk.connection', 'help_desk_central');

    $ticket = (new Ticket)->setConnection('reporting');

    expect($ticket->getConnectionName())->toBe('reporting');
});

it('builds queries against the configured connection', function () {
    expect(Ticket::query()->getConnection()->getName())->toBe('testing');

    config()->set('help-desk.connection', 'help_desk_central');

    expect(Ticket::query()->getConnection()->getName())->toBe('help_desk_central');
});

it('runs the package migrations on the configured connection', function () {
    config()->set('help-desk.connection', 'help_desk_central');

    $migration = new class extends HelpDeskMigration {};

    expect($migration->getConnection())->toBe('help_desk_central');

    config()->set('help-desk.connection', null);

    expect($migration->getConnection())->toBeNull();
});
