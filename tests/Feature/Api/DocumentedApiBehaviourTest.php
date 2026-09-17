<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use JeffersonGoncalves\HelpDesk\Api\HelpDeskSignature;
use JeffersonGoncalves\HelpDesk\Api\Models\ApiTicket;
use JeffersonGoncalves\HelpDesk\Api\Models\ApiTicketAttachment;
use JeffersonGoncalves\HelpDesk\Enums\TicketStatus;
use JeffersonGoncalves\HelpDesk\Exceptions\HelpDeskApiException;
use JeffersonGoncalves\HelpDesk\Facades\HelpDesk;
use JeffersonGoncalves\HelpDesk\Tests\TestUser;

/**
 * The README and the Boost guidelines promise these. Each test is one claim, so
 * a change that makes the documentation wrong fails the suite rather than
 * shipping — which is how resources/boost fell three releases behind before.
 */
beforeEach(function () {
    config()->set('help-desk.driver', 'api');
    config()->set('help-desk.app.key', 'app-a');
    config()->set('help-desk.api.url', 'https://support.example.com');
    config()->set('help-desk.api.secret', 'app-a-secret');
});

function documentedUser(): TestUser
{
    return TestUser::create(['name' => 'Ada Lovelace', 'email' => Str::random(8).'@example.com']);
}

function documentedResponse(array $overrides = []): array
{
    return ['data' => array_merge([
        'uuid' => '550e8400-e29b-41d4-a716-446655440000',
        'reference_number' => 'HD-00042',
        'department_id' => 1,
        'title' => 'Printer offline',
        'description' => 'It stopped printing.',
        'status' => 'open',
        'priority' => 'medium',
        'source' => 'api',
        'app_key' => 'app-a',
        'requester_name' => 'Ada Lovelace',
        'requester_email' => 'ada@example.com',
    ], $overrides)];
}

it('returns what the README snippet says it returns', function () {
    Http::fake(['*' => Http::response(documentedResponse(), 201)]);

    $ticket = HelpDesk::createTicket([
        'department_id' => 1,
        'title' => 'Printer offline',
        'description' => 'It stopped printing.',
    ], documentedUser());

    expect($ticket->reference_number)->toBe('HD-00042')
        ->and($ticket->isOpen())->toBeTrue()
        ->and($ticket->requester_name)->toBe('Ada Lovelace');
});

it('throws for every operator action the documentation lists', function (string $call) {
    Http::fake(['*' => Http::response(documentedResponse(), 200)]);

    $ticket = new ApiTicket;
    $user = documentedUser();

    $actions = [
        'updateTicket' => fn () => HelpDesk::updateTicket($ticket, ['title' => 'x']),
        'changeStatus' => fn () => HelpDesk::changeStatus($ticket, TicketStatus::Resolved, $user),
        'assignTicket' => fn () => HelpDesk::assignTicket($ticket, $user),
        'unassignTicket' => fn () => HelpDesk::unassignTicket($ticket),
        'deleteTicket' => fn () => HelpDesk::deleteTicket($ticket),
        'addNote' => fn () => HelpDesk::addNote($ticket, $user, 'internal'),
        'addWatcher' => fn () => HelpDesk::addWatcher($ticket, $user),
        'removeWatcher' => fn () => HelpDesk::removeWatcher($ticket, $user),
        'createDepartment' => fn () => HelpDesk::createDepartment(['name' => 'x']),
        'deleteAttachment' => fn () => HelpDesk::attachments()->delete(new ApiTicketAttachment),
    ];

    expect($actions[$call])->toThrow(HelpDeskApiException::class);
})->with([
    'updateTicket', 'changeStatus', 'assignTicket',
    'unassignTicket', 'deleteTicket', 'addNote', 'addWatcher', 'removeWatcher',
    'createDepartment', 'deleteAttachment',
]);

it('closes and reopens, which the documentation says a requester may do', function (string $call) {
    Http::fake(['*' => Http::response(documentedResponse(), 200)]);

    $ticket = new ApiTicket;
    $ticket->forceFill(['uuid' => '550e8400-e29b-41d4-a716-446655440000']);

    $user = documentedUser();

    $actions = [
        'closeTicket' => fn () => HelpDesk::closeTicket($ticket, $user),
        'reopenTicket' => fn () => HelpDesk::reopenTicket($ticket, $user),
    ];

    expect($actions[$call])->not->toThrow(HelpDeskApiException::class);
})->with(['closeTicket', 'reopenTicket']);

it('keeps the validation helpers working, as the guidelines say', function () {
    // They read configuration, not the database, so a satellite can still
    // reject a file before trying to send it.
    expect(HelpDesk::attachments()->isAllowedExtension('pdf'))->toBeTrue()
        ->and(HelpDesk::attachments()->isAllowedExtension('exe'))->toBeFalse()
        ->and(HelpDesk::attachments()->isWithinSizeLimit(100))->toBeTrue();
});

it('sends exactly the four headers the scheme documents', function () {
    Http::fake(['*' => Http::response(documentedResponse(), 201)]);

    HelpDesk::createTicket(['department_id' => 1, 'title' => 'x', 'description' => 'y'], documentedUser());

    Http::assertSent(function ($request) {
        $signature = $request->header(HelpDeskSignature::SIGNATURE_HEADER)[0];
        $nonce = $request->header(HelpDeskSignature::NONCE_HEADER)[0];

        return $request->hasHeader(HelpDeskSignature::APP_HEADER)
            && ctype_digit($request->header(HelpDeskSignature::TIMESTAMP_HEADER)[0])
            && strlen($nonce) === 32 && ctype_xdigit($nonce)
            && str_starts_with($signature, 'sha256=')
            && strlen($signature) === 7 + 64;
    });
});

it('hits the documented endpoint path', function () {
    Http::fake(['*' => Http::response(documentedResponse(), 201)]);

    HelpDesk::createTicket(['department_id' => 1, 'title' => 'x', 'description' => 'y'], documentedUser());

    Http::assertSent(fn ($request) => $request->url() === 'https://support.example.com/help-desk/api/tickets');
});
