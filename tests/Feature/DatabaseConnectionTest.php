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

// "help_desk_central" is a real connection for the whole suite, defined in
// TestCase::getEnvironmentSetUp(). This file only points help-desk.connection at
// it, and tests/Pest.php clears that in its afterEach.

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
