<?php

use Illuminate\Support\Facades\Event;
use JeffersonGoncalves\HelpDesk\Api\HelpDeskSigner;
use JeffersonGoncalves\HelpDesk\Enums\TicketStatus;
use JeffersonGoncalves\HelpDesk\Listeners\LogTicketHistory;
use JeffersonGoncalves\HelpDesk\Models\Department;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Models\TicketHistory;

const STATUS_ACTOR = ['type' => 'app-a-user', 'id' => 5, 'name' => 'Ada Lovelace', 'email' => 'ada@example.com'];

function callStatusApi(string $path, array $payload, string $appKey = 'app-a', string $secret = 'app-a-secret')
{
    $body = json_encode($payload);
    $headers = (new HelpDeskSigner)->headersFor('POST', $path, $body, $appKey, $secret);

    $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];

    foreach ($headers as $name => $value) {
        $server['HTTP_'.str_replace('-', '_', strtoupper($name))] = $value;
    }

    return test()->call('POST', $path, [], [], [], $server, $body);
}

function statusTicket(array $overrides = []): Ticket
{
    return Ticket::create(array_merge([
        'department_id' => Department::factory()->create()->id,
        'user_type' => STATUS_ACTOR['type'],
        'user_id' => STATUS_ACTOR['id'],
        'app_key' => 'app-a',
        'title' => 'Printer offline',
        'description' => 'It stopped printing.',
    ], $overrides));
}

it('lets a requester close their own ticket', function () {
    $ticket = statusTicket();

    callStatusApi("/help-desk/api/tickets/{$ticket->uuid}/status", [
        'actor' => STATUS_ACTOR,
        'status' => 'closed',
    ])->assertOk()->assertJsonPath('data.status', 'closed');

    expect($ticket->fresh()->status)->toBe(TicketStatus::Closed)
        ->and($ticket->fresh()->closed_at)->not->toBeNull();
});

it('names the requester on the history entry it writes', function () {
    // Subscribed explicitly, as the other history tests do: the test
    // environment does not run the provider's listener registration.
    Event::subscribe(LogTicketHistory::class);

    $ticket = statusTicket();

    callStatusApi("/help-desk/api/tickets/{$ticket->uuid}/status", [
        'actor' => STATUS_ACTOR,
        'status' => 'closed',
    ])->assertOk();

    // The performer is a model the central application does not have, so the
    // snapshot is the only thing that can name them.
    $history = TicketHistory::where('ticket_id', $ticket->id)->latest('id')->first();

    expect($history->performer_name)->toBe('Ada Lovelace');
});

it('lets a requester reopen their own ticket', function () {
    $ticket = statusTicket(['status' => 'closed']);

    callStatusApi("/help-desk/api/tickets/{$ticket->uuid}/status", [
        'actor' => STATUS_ACTOR,
        'status' => 'open',
    ])->assertOk()->assertJsonPath('data.status', 'open');

    expect($ticket->fresh()->status)->toBe(TicketStatus::Open);
});

it('refuses every status that is not closed or open', function (string $status) {
    $ticket = statusTicket();

    callStatusApi("/help-desk/api/tickets/{$ticket->uuid}/status", [
        'actor' => STATUS_ACTOR,
        'status' => $status,
    ])->assertStatus(422)->assertJsonValidationErrors('status');

    // Nothing moved: the allow-list is checked before anything is written.
    expect($ticket->fresh()->status)->toBe(TicketStatus::Open);
})->with(['resolved', 'in_progress', 'pending', 'on_hold']);

it('answers 409 when the transition table forbids the move', function () {
    $ticket = statusTicket(['status' => 'closed']);

    // Closed may only become Open. Closing it again is well formed and
    // permitted for this caller, but not legal from where the ticket is.
    callStatusApi("/help-desk/api/tickets/{$ticket->uuid}/status", [
        'actor' => STATUS_ACTOR,
        'status' => 'closed',
    ])->assertStatus(409);
});

it('honours allow_reopen, which the transition table already reads', function () {
    config()->set('help-desk.ticket.allow_reopen', false);

    $ticket = statusTicket(['status' => 'closed']);

    callStatusApi("/help-desk/api/tickets/{$ticket->uuid}/status", [
        'actor' => STATUS_ACTOR,
        'status' => 'open',
    ])->assertStatus(409);

    expect($ticket->fresh()->status)->toBe(TicketStatus::Closed);
});

it('refuses a ticket belonging to another application', function () {
    $ticket = statusTicket(['app_key' => 'app-b']);

    callStatusApi("/help-desk/api/tickets/{$ticket->uuid}/status", [
        'actor' => STATUS_ACTOR,
        'status' => 'closed',
    ])->assertNotFound();

    expect($ticket->fresh()->status)->toBe(TicketStatus::Open);
});

it('refuses a ticket belonging to another user of the same application', function () {
    $ticket = statusTicket(['user_id' => 9]);

    callStatusApi("/help-desk/api/tickets/{$ticket->uuid}/status", [
        'actor' => STATUS_ACTOR,
        'status' => 'closed',
    ])->assertNotFound();

    expect($ticket->fresh()->status)->toBe(TicketStatus::Open);
});

it('refuses an unsigned request', function () {
    $ticket = statusTicket();

    test()->postJson("/help-desk/api/tickets/{$ticket->uuid}/status", [
        'actor' => STATUS_ACTOR,
        'status' => 'closed',
    ])->assertUnauthorized();

    expect($ticket->fresh()->status)->toBe(TicketStatus::Open);
});

it('refuses a request signed with the wrong secret', function () {
    $ticket = statusTicket();

    callStatusApi("/help-desk/api/tickets/{$ticket->uuid}/status", [
        'actor' => STATUS_ACTOR,
        'status' => 'closed',
    ], 'app-a', 'app-b-secret')->assertUnauthorized();

    expect($ticket->fresh()->status)->toBe(TicketStatus::Open);
});
