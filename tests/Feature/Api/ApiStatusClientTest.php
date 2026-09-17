<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use JeffersonGoncalves\HelpDesk\Api\Models\ApiTicket;
use JeffersonGoncalves\HelpDesk\Enums\TicketStatus;
use JeffersonGoncalves\HelpDesk\Exceptions\HelpDeskApiException;
use JeffersonGoncalves\HelpDesk\Exceptions\InvalidStatusTransitionException;
use JeffersonGoncalves\HelpDesk\Facades\HelpDesk;
use JeffersonGoncalves\HelpDesk\Tests\TestUser;

beforeEach(function () {
    config()->set('help-desk.driver', 'api');
    config()->set('help-desk.app.key', 'app-a');
    config()->set('help-desk.api.url', 'https://support.example.com');
    config()->set('help-desk.api.secret', 'app-a-secret');
});

function statusActor(): TestUser
{
    return TestUser::create(['name' => 'Ada Lovelace', 'email' => Str::random(8).'@example.com']);
}

function statusResponse(string $status = 'closed'): array
{
    return ['data' => [
        'uuid' => '550e8400-e29b-41d4-a716-446655440000',
        'reference_number' => 'HD-00042',
        'department_id' => 1,
        'title' => 'Printer offline',
        'description' => 'It stopped printing.',
        'status' => $status,
        'priority' => 'medium',
        'source' => 'api',
        'app_key' => 'app-a',
        'requester_name' => 'Ada Lovelace',
        'requester_email' => 'ada@example.com',
    ]];
}

/**
 * A ticket as it arrives on a satellite: hydrated, never persisted. Built
 * directly rather than fetched, so a test about writing status is not also a
 * test about reading one.
 */
function pendingTicket(): ApiTicket
{
    $ticket = new ApiTicket;
    $ticket->forceFill(statusResponse('open')['data']);
    $ticket->exists = true;
    $ticket->syncOriginal();

    return $ticket;
}

it('closes a ticket over the wire', function () {
    $ticket = pendingTicket();

    Http::fake(['*' => Http::response(statusResponse('closed'))]);

    $closed = HelpDesk::closeTicket($ticket, statusActor());

    expect($closed->status)->toBe(TicketStatus::Closed);

    Http::assertSent(function ($request) use ($ticket) {
        $body = json_decode($request->body(), true);

        return $request->method() === 'POST'
            && $request->url() === "https://support.example.com/help-desk/api/tickets/{$ticket->uuid}/status"
            && $body['status'] === 'closed'
            && $body['actor']['name'] === 'Ada Lovelace';
    });
});

it('reopens a ticket over the wire', function () {
    $ticket = pendingTicket();

    Http::fake(['*' => Http::response(statusResponse('open'))]);

    HelpDesk::reopenTicket($ticket, statusActor());

    Http::assertSent(fn ($request) => json_decode($request->body(), true)['status'] === 'open');
});

it('refuses an operator status without sending anything', function () {
    $ticket = pendingTicket();

    Http::fake(['*' => Http::response(statusResponse())]);

    expect(fn () => HelpDesk::changeStatus($ticket, TicketStatus::InProgress, statusActor()))
        ->toThrow(HelpDeskApiException::class);

    // Refused locally, so the central application never sees a request it
    // would have had to reject.
    Http::assertNothingSent();
});

it('raises the documented exception when the central application answers 409', function () {
    $ticket = pendingTicket();

    Http::fake(['*' => Http::response(['message' => 'Cannot change status from Closed to Closed.'], 409)]);

    expect(fn () => HelpDesk::closeTicket($ticket, statusActor()))
        ->toThrow(InvalidStatusTransitionException::class, 'Cannot change status from Closed to Closed.');
});
