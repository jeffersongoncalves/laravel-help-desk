<?php

use JeffersonGoncalves\HelpDesk\Contracts\AttachmentRepository;
use JeffersonGoncalves\HelpDesk\Contracts\CommentRepository;
use JeffersonGoncalves\HelpDesk\Contracts\DepartmentRepository;
use JeffersonGoncalves\HelpDesk\Contracts\TicketRepository;
use JeffersonGoncalves\HelpDesk\Exceptions\UnsupportedDriverException;
use JeffersonGoncalves\HelpDesk\Facades\HelpDesk;
use JeffersonGoncalves\HelpDesk\Services\AttachmentService;
use JeffersonGoncalves\HelpDesk\Services\CommentService;
use JeffersonGoncalves\HelpDesk\Services\DepartmentService;
use JeffersonGoncalves\HelpDesk\Services\TicketService;

// A list of pairs, not a map: an associative dataset passes the value only,
// and both halves are the point here.
$contracts = [
    [TicketRepository::class, TicketService::class],
    [CommentRepository::class, CommentService::class],
    [DepartmentRepository::class, DepartmentService::class],
    [AttachmentRepository::class, AttachmentService::class],
];

it('resolves the database implementation by default', function (string $contract, string $implementation) {
    expect(config('help-desk.driver'))->toBe('database')
        ->and(app($contract))->toBeInstanceOf($implementation);
})->with($contracts);

it('hands the manager the contracts, not the concrete services', function () {
    expect(HelpDesk::tickets())->toBeInstanceOf(TicketRepository::class)
        ->and(HelpDesk::comments())->toBeInstanceOf(CommentRepository::class)
        ->and(HelpDesk::departments())->toBeInstanceOf(DepartmentRepository::class)
        ->and(HelpDesk::attachments())->toBeInstanceOf(AttachmentRepository::class);
});

it('reads the driver when the contract is resolved, not at registration', function () {
    config()->set('help-desk.driver', 'nonsense');

    // The binding was registered while the driver was still 'database', so this
    // only throws if the driver is read lazily — which is what lets a test, or a
    // config cache written after boot, change it.
    expect(fn () => app(TicketRepository::class))
        ->toThrow(UnsupportedDriverException::class);
});

it('names the driver and the valid values when the driver is unknown', function () {
    config()->set('help-desk.driver', 'carrier-pigeon');

    expect(fn () => app(TicketRepository::class))
        ->toThrow(
            UnsupportedDriverException::class,
            "Unsupported help desk driver 'carrier-pigeon'. Set help-desk.driver to one of: database, api.",
        );
});

it('rejects a driver that is not a string', function () {
    config()->set('help-desk.driver', ['database']);

    expect(fn () => app(TicketRepository::class))
        ->toThrow(UnsupportedDriverException::class, 'Unsupported help desk driver array.');
});

it('keeps the concrete services resolvable for anything that asks for them', function (string $contract, string $implementation) {
    // The package binds them itself, and a consumer may type-hint one directly.
    expect(app($implementation))->toBeInstanceOf($implementation)
        ->and(app($implementation))->toBeInstanceOf($contract);
})->with($contracts);
