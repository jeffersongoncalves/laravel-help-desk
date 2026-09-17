<?php

use JeffersonGoncalves\HelpDesk\Api\HelpDeskSigner;
use JeffersonGoncalves\HelpDesk\Models\Category;
use JeffersonGoncalves\HelpDesk\Models\Department;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Models\TicketComment;

const APP_A_SECRET = 'app-a-secret';

const APP_B_SECRET = 'app-b-secret';

const ADA = ['type' => 'app-a-user', 'id' => 5, 'name' => 'Ada Lovelace', 'email' => 'ada@example.com'];

const GRACE = ['type' => 'app-a-user', 'id' => 9, 'name' => 'Grace Hopper', 'email' => 'grace@example.com'];

/**
 * Signs and sends, so every test goes through the real middleware rather than
 * a bypassed route.
 */
function callApi(string $method, string $path, array $payload = [], string $appKey = 'app-a', string $secret = APP_A_SECRET)
{
    $body = $payload === [] ? '' : json_encode($payload);

    $headers = (new HelpDeskSigner)->headersFor($method, $path, $body, $appKey, $secret);
    $headers['Content-Type'] = 'application/json';
    $headers['Accept'] = 'application/json';

    $server = [];

    foreach ($headers as $name => $value) {
        $server['HTTP_'.str_replace('-', '_', strtoupper($name))] = $value;
    }

    $server['CONTENT_TYPE'] = 'application/json';

    return test()->call($method, $path, [], [], [], $server, $body);
}

function apiTicket(array $actor, string $appKey = 'app-a', ?Department $department = null): Ticket
{
    return Ticket::create([
        'department_id' => ($department ?? Department::factory()->create())->id,
        'user_type' => $actor['type'],
        'user_id' => $actor['id'],
        'app_key' => $appKey,
        'title' => 'Printer offline',
        'description' => 'It stopped printing.',
    ]);
}

it('opens a ticket, stamping the app key from the signature', function () {
    $department = Department::factory()->create();

    $response = callApi('POST', '/help-desk/api/tickets', [
        'actor' => ADA,
        'department_id' => $department->id,
        'title' => 'Printer offline',
        'description' => 'It stopped printing.',
        // A satellite claiming another application's key in the body changes
        // nothing: the signature decides.
        'app_key' => 'app-b',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.app_key', 'app-a')
        ->assertJsonPath('data.requester_name', 'Ada Lovelace')
        ->assertJsonPath('data.source', 'api');

    $ticket = Ticket::firstOrFail();

    expect($ticket->app_key)->toBe('app-a')
        ->and($ticket->user_type)->toBe('app-a-user')
        ->and($ticket->user_id)->toBe(5)
        ->and($ticket->metadata['requester'])->toBe(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);
});

it('lists only the caller application and the acting user', function () {
    $mine = apiTicket(ADA);
    apiTicket(GRACE);                          // same app, another user
    apiTicket(ADA + [], 'app-b');              // another app, same actor keys

    $response = callApi('GET', '/help-desk/api/tickets?'.http_build_query(['actor' => ADA]));

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.uuid', $mine->uuid);
});

it('returns 404 for a ticket belonging to another application', function () {
    $theirs = apiTicket(ADA, 'app-b');

    callApi('GET', '/help-desk/api/tickets/'.$theirs->uuid.'?'.http_build_query(['actor' => ADA]))
        ->assertNotFound();
});

it('returns 404 for another user of the same application', function () {
    $theirs = apiTicket(GRACE);

    callApi('GET', '/help-desk/api/tickets/'.$theirs->uuid.'?'.http_build_query(['actor' => ADA]))
        ->assertNotFound();
});

it('never returns an internal note', function () {
    $ticket = apiTicket(ADA);

    TicketComment::create([
        'ticket_id' => $ticket->id,
        'body' => 'Public reply.',
        'type' => 'reply',
        'is_internal' => false,
    ]);

    TicketComment::create([
        'ticket_id' => $ticket->id,
        'body' => 'Escalating to senior engineer.',
        'type' => 'note',
        'is_internal' => true,
    ]);

    $response = callApi('GET', '/help-desk/api/tickets/'.$ticket->uuid.'?'.http_build_query(['actor' => ADA]));

    $response->assertOk()->assertJsonCount(1, 'data.comments');

    expect($response->json('data.comments.0.body'))->toBe('Public reply.')
        ->and($response->content())->not->toContain('Escalating');
});

it('adds a public reply, never an internal note', function () {
    $ticket = apiTicket(ADA);

    callApi('POST', "/help-desk/api/tickets/{$ticket->uuid}/comments", [
        'actor' => ADA,
        'body' => 'Any news?',
        'is_internal' => true,   // ignored: not a field the request accepts
        'type' => 'note',
    ])->assertCreated();

    $comment = TicketComment::firstOrFail();

    expect($comment->is_internal)->toBeFalse()
        ->and($comment->type->value)->toBe('reply')
        ->and($comment->author_name)->toBe('Ada Lovelace');
});

it('rejects an actor type the application did not register', function () {
    $department = Department::factory()->create();

    callApi('POST', '/help-desk/api/tickets', [
        'actor' => ['type' => 'app-b-user', 'id' => 5, 'name' => 'Impostor'],
        'department_id' => $department->id,
        'title' => 'Printer offline',
        'description' => 'It stopped printing.',
    ])->assertStatus(422)->assertJsonValidationErrors('actor.type');
});

it('rejects a request with no actor at all', function () {
    $department = Department::factory()->create();

    callApi('POST', '/help-desk/api/tickets', [
        'department_id' => $department->id,
        'title' => 'Printer offline',
        'description' => 'It stopped printing.',
    ])->assertStatus(422)->assertJsonValidationErrors(['actor.type', 'actor.id']);
});

it('rejects an inactive department', function () {
    $department = Department::factory()->create(['is_active' => false]);

    callApi('POST', '/help-desk/api/tickets', [
        'actor' => ADA,
        'department_id' => $department->id,
        'title' => 'Printer offline',
        'description' => 'It stopped printing.',
    ])->assertStatus(422)->assertJsonValidationErrors('department_id');
});

it('serves the departments and categories a create form needs', function () {
    $active = Department::factory()->create(['is_active' => true]);
    Department::factory()->create(['is_active' => false]);

    Category::create(['department_id' => $active->id, 'name' => 'Billing', 'is_active' => true]);
    Category::create(['department_id' => $active->id, 'name' => 'Hidden', 'is_active' => false]);

    callApi('GET', '/help-desk/api/departments')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $active->id);

    callApi('GET', "/help-desk/api/departments/{$active->id}/categories")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Billing');
});

it('refuses an unsigned request', function () {
    test()->postJson('/help-desk/api/tickets', ['actor' => ADA])->assertUnauthorized();
});

it('refuses a request signed by another application for this one', function () {
    $department = Department::factory()->create();

    callApi('POST', '/help-desk/api/tickets', [
        'actor' => ADA,
        'department_id' => $department->id,
        'title' => 'Printer offline',
        'description' => 'It stopped printing.',
    ], 'app-a', APP_B_SECRET)->assertUnauthorized();
});

it('publishes only the fields the resource lists', function () {
    $ticket = apiTicket(ADA);

    $response = callApi('GET', '/help-desk/api/tickets/'.$ticket->uuid.'?'.http_build_query(['actor' => ADA]));

    // A column added later must not appear without someone deciding it should.
    expect(array_keys($response->json('data')))->toBe([
        'uuid', 'reference_number', 'department_id', 'category_id', 'title',
        'description', 'status', 'priority', 'source', 'app_key',
        'requester_name', 'requester_email', 'closed_at', 'due_at',
        'last_replied_at', 'created_at', 'updated_at', 'comments', 'attachments',
    ]);
});
