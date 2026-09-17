<?php

use JeffersonGoncalves\HelpDesk\Api\HelpDeskSigner;
use JeffersonGoncalves\HelpDesk\Enums\TicketPriority;
use JeffersonGoncalves\HelpDesk\Enums\TicketStatus;
use JeffersonGoncalves\HelpDesk\Models\Department;
use JeffersonGoncalves\HelpDesk\Models\Ticket;

const FILTERER = ['type' => 'app-a-user', 'id' => 5, 'name' => 'Ada Lovelace', 'email' => 'ada@example.com'];

function callFilterApi(array $query)
{
    $path = '/help-desk/api/tickets?'.http_build_query($query + ['actor' => FILTERER]);

    $headers = (new HelpDeskSigner)->headersFor('GET', $path, '', 'app-a', 'app-a-secret');

    $server = ['HTTP_ACCEPT' => 'application/json'];

    foreach ($headers as $name => $value) {
        $server['HTTP_'.str_replace('-', '_', strtoupper($name))] = $value;
    }

    return test()->call('GET', $path, [], [], [], $server, '');
}

function filterableTicket(array $attributes = [], array $actor = FILTERER, string $appKey = 'app-a'): Ticket
{
    return Ticket::create($attributes + [
        'department_id' => Department::factory()->create()->id,
        'user_type' => $actor['type'],
        'user_id' => $actor['id'],
        'app_key' => $appKey,
        'title' => 'Printer offline',
        'description' => 'It stopped printing.',
    ]);
}

it('filters by status, including ones a requester can never set', function () {
    filterableTicket(['title' => 'Open one', 'status' => TicketStatus::Open]);
    filterableTicket(['title' => 'Working on it', 'status' => TicketStatus::InProgress]);
    filterableTicket(['title' => 'Done', 'status' => TicketStatus::Closed]);

    // in_progress is an operator-only transition, but the requester sees it on
    // their own tickets, so filtering by it has to work.
    $response = callFilterApi(['status' => ['open', 'in_progress']]);

    $response->assertOk()->assertJsonCount(2, 'data');

    expect(collect($response->json('data'))->pluck('title')->sort()->values()->all())
        ->toBe(['Open one', 'Working on it']);
});

it('filters by priority', function () {
    filterableTicket(['title' => 'Urgent one', 'priority' => TicketPriority::Urgent]);
    filterableTicket(['title' => 'Low one', 'priority' => TicketPriority::Low]);

    $response = callFilterApi(['priority' => ['urgent']]);

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Urgent one');
});

it('searches the title and the reference number, and nothing else', function () {
    $matching = filterableTicket(['title' => 'Scanner jams on page two']);
    filterableTicket(['title' => 'Printer offline', 'description' => 'The scanner is fine.']);

    expect(collect(callFilterApi(['q' => 'scanner'])->json('data'))->pluck('uuid')->all())
        // Case-insensitively, and not over the description: it is rich text on
        // the way in, so a hit there is one the user cannot see in the row.
        ->toBe([$matching->uuid])
        ->and(collect(callFilterApi(['q' => $matching->reference_number])->json('data'))->pluck('uuid')->all())
        ->toBe([$matching->uuid]);
});

it('treats a wildcard in the search term as a character', function () {
    $matching = filterableTicket(['title' => 'Disk is 90% full']);
    filterableTicket(['title' => 'Printer offline']);

    // Unescaped, `%` would match every title and the list would look searched.
    expect(collect(callFilterApi(['q' => '90%'])->json('data'))->pluck('uuid')->all())
        ->toBe([$matching->uuid]);
});

it('sorts by priority in severity order, not alphabetically', function () {
    filterableTicket(['title' => 'Low', 'priority' => TicketPriority::Low]);
    filterableTicket(['title' => 'Urgent', 'priority' => TicketPriority::Urgent]);
    filterableTicket(['title' => 'Medium', 'priority' => TicketPriority::Medium]);

    // Alphabetically this would be low, medium, urgent.
    $response = callFilterApi(['sort' => 'priority', 'direction' => 'desc']);

    expect(collect($response->json('data'))->pluck('title')->all())
        ->toBe(['Urgent', 'Medium', 'Low']);
});

it('sorts by a column the allow-list names', function () {
    $older = filterableTicket(['title' => 'Older', 'last_replied_at' => now()->subDay()]);
    $newer = filterableTicket(['title' => 'Newer', 'last_replied_at' => now()]);

    expect(collect(callFilterApi(['sort' => 'last_replied_at', 'direction' => 'asc'])->json('data'))->pluck('uuid')->all())
        ->toBe([$older->uuid, $newer->uuid]);
});

it('refuses a sort column that is not on the allow-list', function () {
    filterableTicket();

    // A caller-supplied column reaches the query builder, so the server checks
    // too — the client refusing first is a convenience, not the boundary.
    callFilterApi(['sort' => 'user_id'])->assertStatus(422);
    callFilterApi(['sort' => 'created_at', 'direction' => 'sideways'])->assertStatus(422);
    callFilterApi(['status' => ['nonsense']])->assertStatus(422);
});

it('keeps every filter inside the caller scope', function () {
    filterableTicket(['title' => 'Mine', 'status' => TicketStatus::Open]);
    filterableTicket(
        ['title' => 'Theirs', 'status' => TicketStatus::Open],
        ['type' => 'app-a-user', 'id' => 9],
    );
    filterableTicket(['title' => 'Other app', 'status' => TicketStatus::Open], FILTERER, 'app-b');

    $response = callFilterApi(['status' => ['open'], 'sort' => 'created_at']);

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Mine');
});
