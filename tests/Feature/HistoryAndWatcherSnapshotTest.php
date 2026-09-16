<?php

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use JeffersonGoncalves\HelpDesk\Enums\HistoryAction;
use JeffersonGoncalves\HelpDesk\Enums\TicketStatus;
use JeffersonGoncalves\HelpDesk\Facades\HelpDesk;
use JeffersonGoncalves\HelpDesk\Listeners\LogTicketHistory;
use JeffersonGoncalves\HelpDesk\Models\Department;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Models\TicketHistory;
use JeffersonGoncalves\HelpDesk\Models\TicketWatcher;
use JeffersonGoncalves\HelpDesk\Tests\TestUser;

function person(string $name = 'Ada Lovelace'): TestUser
{
    return TestUser::create(['name' => $name, 'email' => Str::random(8).'@example.com']);
}

function openTicket(TestUser $user): Ticket
{
    return HelpDesk::createTicket([
        'department_id' => Department::factory()->create()->id,
        'title' => 'Printer offline',
        'description' => 'It stopped printing.',
    ], $user);
}

function entryFor(Ticket $ticket, HistoryAction $action): TicketHistory
{
    return $ticket->history()->where('action', $action)->firstOrFail();
}

beforeEach(fn () => Event::subscribe(LogTicketHistory::class));

afterEach(fn () => Relation::morphMap([], false));

it('snapshots a performer it holds the model for', function () {
    $user = person();
    $ticket = openTicket($user);

    HelpDesk::changeStatus($ticket, TicketStatus::InProgress, $user);

    expect(entryFor($ticket, HistoryAction::StatusChanged)->metadata['performer'])
        ->toBe(['name' => $user->name, 'email' => $user->email]);
});

it('copies the requester snapshot onto the created entry', function () {
    $user = person();
    $ticket = openTicket($user);

    // Only the stored morph keys are available to that handler, so it takes the
    // snapshot the ticket already carries rather than loading the model back.
    expect(entryFor($ticket, HistoryAction::Created)->metadata['performer'])
        ->toBe(['name' => $user->name, 'email' => $user->email]);
});

it('copies the author snapshot onto the comment entry, keeping comment_id', function () {
    $user = person();
    $ticket = openTicket($user);

    $comment = HelpDesk::addComment($ticket, $user, 'Public reply.');

    $entry = entryFor($ticket, HistoryAction::CommentAdded);

    expect($entry->metadata['comment_id'])->toBe($comment->id)
        ->and($entry->metadata['performer'])->toBe(['name' => $user->name, 'email' => $user->email]);
});

it('writes no performer snapshot for a system action', function () {
    $ticket = openTicket(person());

    HelpDesk::changeStatus($ticket, TicketStatus::InProgress);

    $entry = entryFor($ticket, HistoryAction::StatusChanged);

    expect($entry->metadata)->not->toHaveKey('performer')
        ->and($entry->performer_name)->toBeNull();
});

it('falls back to the snapshot when the performer model is not installed here', function () {
    $user = person();
    $ticket = openTicket($user);

    HelpDesk::changeStatus($ticket, TicketStatus::InProgress, $user);

    $entry = entryFor($ticket, HistoryAction::StatusChanged);
    TicketHistory::query()->whereKey($entry->getKey())->update(['performer_type' => 'app-b-user']);
    $entry = $entry->fresh();

    expect($entry->resolvedPerformer())->toBeNull()
        ->and($entry->performer_name)->toBe($user->name)
        ->and($entry->performer_email)->toBe($user->email);
});

it('reads the performer from the live model when it resolves', function () {
    $user = person();
    $ticket = openTicket($user);

    HelpDesk::changeStatus($ticket, TicketStatus::InProgress, $user);

    $user->update(['name' => 'Ada King']);

    expect(entryFor($ticket, HistoryAction::StatusChanged)->performer_name)->toBe('Ada King');
});

it('snapshots a watcher when it is added', function () {
    $user = person();
    $ticket = openTicket(person('Requester'));

    HelpDesk::addWatcher($ticket, $user);

    expect($ticket->watchers()->first()->metadata['watcher'])
        ->toBe(['name' => $user->name, 'email' => $user->email]);
});

it('falls back to the snapshot when the watcher model is not installed here', function () {
    $user = person();
    $ticket = openTicket(person('Requester'));

    HelpDesk::addWatcher($ticket, $user);

    $row = $ticket->watchers()->first();
    TicketWatcher::query()->whereKey($row->getKey())->update(['watcher_type' => 'app-b-user']);
    $row = $row->fresh();

    expect($row->resolvedWatcher())->toBeNull()
        ->and($row->watcher_name)->toBe($user->name)
        ->and($row->watcher_email)->toBe($user->email);
});

it('does not duplicate a watcher that is added twice', function () {
    $user = person();
    $ticket = openTicket(person('Requester'));

    HelpDesk::addWatcher($ticket, $user);
    HelpDesk::addWatcher($ticket, $user);

    expect($ticket->watchers()->count())->toBe(1)
        ->and($ticket->watchers()->first()->watcher_name)->toBe($user->name);
});

it('returns null for rows written before the snapshot existed', function () {
    $user = person();
    $ticket = openTicket($user);

    HelpDesk::addWatcher($ticket, $user);
    HelpDesk::changeStatus($ticket, TicketStatus::InProgress, $user);

    $entry = entryFor($ticket, HistoryAction::StatusChanged);
    TicketHistory::query()->whereKey($entry->getKey())
        ->update(['performer_type' => 'app-b-user', 'metadata' => null]);

    $row = $ticket->watchers()->first();
    TicketWatcher::query()->whereKey($row->getKey())
        ->update(['watcher_type' => 'app-b-user', 'metadata' => null]);

    expect($entry->fresh()->performer_name)->toBeNull()
        ->and($row->fresh()->watcher_name)->toBeNull();
});
