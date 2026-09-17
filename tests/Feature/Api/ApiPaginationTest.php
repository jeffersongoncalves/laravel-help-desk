<?php

use Illuminate\Support\Facades\Http;
use JeffersonGoncalves\HelpDesk\Facades\HelpDesk;
use JeffersonGoncalves\HelpDesk\Models\Category;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Tests\TestUser;

beforeEach(function () {
    config()->set('help-desk.driver', 'api');
    config()->set('help-desk.app.key', 'app-a');
    config()->set('help-desk.api.url', 'https://support.example.com');
    config()->set('help-desk.api.secret', 'app-a-secret');
});

function paginatedUser(): TestUser
{
    return TestUser::create(['name' => 'Ada Lovelace', 'email' => str()->random(8).'@example.com']);
}

/**
 * What the endpoint actually returns: a resource collection over a paginator,
 * so `data` is one page and `meta` says how many there are.
 */
function paginatedTickets(int $onThisPage, int $total, int $currentPage = 1, int $perPage = 25): array
{
    return [
        'data' => collect(range(1, $onThisPage))->map(fn (int $n) => [
            'uuid' => sprintf('550e8400-e29b-41d4-a716-4466554400%02d', $n),
            'reference_number' => sprintf('HD-%05d', $n),
            'department_id' => 1,
            'title' => "Ticket {$n}",
            'description' => 'It stopped printing.',
            'status' => 'open',
            'priority' => 'medium',
            'source' => 'api',
            'app_key' => 'app-a',
            'requester_name' => 'Ada Lovelace',
            'requester_email' => 'ada@example.com',
        ])->all(),
        'meta' => [
            'current_page' => $currentPage,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => (int) ceil($total / $perPage),
        ],
    ];
}

it('keeps the totals the endpoint reports instead of showing one page as everything', function () {
    Http::fake(['*' => Http::response(paginatedTickets(onThisPage: 25, total: 63))]);

    $tickets = HelpDesk::tickets()->forActor(paginatedUser());

    // Before this, `data` was returned on its own: a satellite with 63 tickets
    // was shown 25 and had no way to tell.
    expect($tickets->total())->toBe(63)
        ->and($tickets->count())->toBe(25)
        ->and($tickets->lastPage())->toBe(3)
        ->and($tickets->currentPage())->toBe(1)
        ->and($tickets->items()[0])->toBeInstanceOf(Ticket::class);
});

it('asks for the page it was given', function () {
    Http::fake(['*' => Http::response(paginatedTickets(onThisPage: 3, total: 63, currentPage: 3, perPage: 10))]);

    $tickets = HelpDesk::tickets()->forActor(paginatedUser(), perPage: 10, page: 3);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'per_page=10')
            && str_contains($request->url(), 'page=3');
    });

    expect($tickets->currentPage())->toBe(3);
});

it('falls back to what it was sent when the response carries no meta', function () {
    $payload = paginatedTickets(onThisPage: 4, total: 4);
    unset($payload['meta']);

    Http::fake(['*' => Http::response($payload)]);

    $tickets = HelpDesk::tickets()->forActor(paginatedUser());

    // One page of what arrived, not an empty list.
    expect($tickets->total())->toBe(4)
        ->and($tickets->count())->toBe(4);
});

it('hydrates categories into models, as the database driver returns them', function () {
    Http::fake(['*' => Http::response(['data' => [
        ['id' => 7, 'department_id' => 1, 'name' => 'Billing', 'slug' => 'billing'],
    ]])]);

    $categories = HelpDesk::departments()->categoriesFor(1);

    expect($categories->first())->toBeInstanceOf(Category::class)
        ->and($categories->first()->name)->toBe('Billing')
        ->and($categories->first()->exists)->toBeTrue()
        ->and($categories->first()->isDirty())->toBeFalse();
});
