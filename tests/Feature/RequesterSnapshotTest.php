<?php

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Notification;
use JeffersonGoncalves\HelpDesk\Models\Department;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Models\TicketComment;
use JeffersonGoncalves\HelpDesk\Notifications\TicketCreatedNotification;
use JeffersonGoncalves\HelpDesk\Services\CommentService;
use JeffersonGoncalves\HelpDesk\Services\TicketService;
use JeffersonGoncalves\HelpDesk\Tests\TestUser;

/**
 * Rewrites the stored morph type to one this application cannot resolve, the
 * way a ticket written by another application sharing the database looks from
 * the central admin application.
 */
function makeRequesterForeign(Ticket $ticket): Ticket
{
    $ticket->newQuery()->whereKey($ticket->getKey())->update(['user_type' => 'app-b-user']);

    return $ticket->fresh();
}

function makeTicket(array $attributes = []): Ticket
{
    $department = Department::factory()->create();

    return app(TicketService::class)->create(array_merge([
        'department_id' => $department->id,
        'title' => 'Printer offline',
        'description' => 'It stopped printing.',
    ], $attributes), TestUser::create([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ]));
}

it('snapshots the requester on the ticket', function () {
    $ticket = makeTicket();

    expect($ticket->metadata['requester'])->toBe([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ]);
});

it('keeps metadata passed by the caller alongside the snapshot', function () {
    $ticket = makeTicket(['metadata' => ['origin' => 'kiosk']]);

    expect($ticket->metadata)
        ->toHaveKey('origin', 'kiosk')
        ->toHaveKey('requester.name', 'Ada Lovelace');
});

it('reads the requester from the live model when it resolves', function () {
    $ticket = makeTicket();

    $ticket->requester()->update(['name' => 'Ada King']);

    expect($ticket->fresh()->requester_name)->toBe('Ada King')
        ->and($ticket->fresh()->requester_email)->toBe('ada@example.com');
});

it('falls back to the snapshot when the requester model is not installed here', function () {
    $ticket = makeRequesterForeign(makeTicket());

    expect($ticket->requester())->toBeNull()
        ->and($ticket->requester_name)->toBe('Ada Lovelace')
        ->and($ticket->requester_email)->toBe('ada@example.com');
});

it('resolves a requester reachable through a registered morph alias', function () {
    $ticket = makeRequesterForeign(makeTicket());

    Relation::morphMap(['app-b-user' => TestUser::class]);

    expect($ticket->requester())->not->toBeNull()
        ->and($ticket->requester_name)->toBe('Ada Lovelace');
});

afterEach(fn () => Relation::morphMap([], false));

it('returns null for a requester with neither a model nor a snapshot', function () {
    $ticket = makeRequesterForeign(makeTicket());
    $ticket->update(['metadata' => null]);

    expect($ticket->requester_name)->toBeNull()
        ->and($ticket->requester_email)->toBeNull();
});

it('snapshots the author on a comment', function () {
    $ticket = makeTicket();

    $author = TestUser::create([
        'name' => 'Grace Hopper',
        'email' => 'grace@example.com',
    ]);

    $comment = app(CommentService::class)->addReply($ticket, $author, 'Have you tried the other tray?');

    expect($comment->metadata['author'])->toBe([
        'name' => 'Grace Hopper',
        'email' => 'grace@example.com',
    ])
        ->and($comment->author_name)->toBe('Grace Hopper');
});

it('falls back to the snapshot for a comment author not installed here', function () {
    $ticket = makeTicket();

    $author = TestUser::create([
        'name' => 'Grace Hopper',
        'email' => 'grace@example.com',
    ]);

    $comment = app(CommentService::class)->addReply($ticket, $author, 'Have you tried the other tray?');

    TicketComment::query()->whereKey($comment->getKey())->update(['author_type' => 'app-b-user']);

    $comment = $comment->fresh();

    expect($comment->resolvedAuthor())->toBeNull()
        ->and($comment->author_name)->toBe('Grace Hopper')
        ->and($comment->author_email)->toBe('grace@example.com');
});

it('notifies a foreign requester by email alone', function () {
    Notification::fake();

    $ticket = makeRequesterForeign(makeTicket());

    $ticket->notifyRequester(new TicketCreatedNotification($ticket));

    Notification::assertSentOnDemand(
        TicketCreatedNotification::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'ada@example.com'
    );
});

it('notifies a local requester through their model', function () {
    Notification::fake();

    $ticket = makeTicket();

    $ticket->notifyRequester(new TicketCreatedNotification($ticket));

    Notification::assertSentTo($ticket->requester(), TicketCreatedNotification::class);
});

it('sends nothing when a foreign requester has no snapshot email', function () {
    Notification::fake();

    $ticket = makeRequesterForeign(makeTicket());
    $ticket->update(['metadata' => null]);

    $ticket->notifyRequester(new TicketCreatedNotification($ticket));

    Notification::assertNothingSent();
});
