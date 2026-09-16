<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JeffersonGoncalves\HelpDesk\Enums\HistoryAction;
use JeffersonGoncalves\HelpDesk\Enums\TicketStatus;
use JeffersonGoncalves\HelpDesk\Exceptions\InvalidStatusTransitionException;
use JeffersonGoncalves\HelpDesk\Exceptions\TicketNotFoundException;
use JeffersonGoncalves\HelpDesk\Facades\HelpDesk;
use JeffersonGoncalves\HelpDesk\Listeners\LogTicketHistory;
use JeffersonGoncalves\HelpDesk\Models\Department;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Tests\TestUser;

/**
 * The README promises these calls work. Each test is one snippet from it, so a
 * rename that breaks the documentation fails the suite instead of shipping.
 */
function readmeUser(string $name = 'Ada Lovelace'): TestUser
{
    return TestUser::create(['name' => $name, 'email' => Str::random(8).'@example.com']);
}

function readmeTicket(TestUser $user): Ticket
{
    return HelpDesk::createTicket([
        'department_id' => Department::factory()->create()->id,
        'title' => 'Printer offline',
        'description' => 'It stopped printing.',
    ], $user);
}

it('exposes the ticket state helpers', function () {
    $ticket = readmeTicket(readmeUser());

    expect($ticket->isOpen())->toBeTrue()
        ->and($ticket->isClosed())->toBeFalse()
        ->and($ticket->isResolved())->toBeFalse()
        ->and($ticket->isAssigned())->toBeFalse()
        ->and($ticket->isOverdue())->toBeFalse();

    $ticket->update(['due_at' => now()->subDay()]);

    expect($ticket->isOverdue())->toBeTrue();
});

it('exposes the comment scopes and kind helpers', function () {
    $user = readmeUser();
    $ticket = readmeTicket($user);

    HelpDesk::addComment($ticket, $user, 'Public reply.');
    HelpDesk::addNote($ticket, $user, 'Internal note.');

    expect($ticket->comments()->public()->count())->toBe(1)
        ->and($ticket->comments()->internal()->count())->toBe(1)
        ->and($ticket->comments()->replies()->count())->toBe(1)
        ->and($ticket->comments()->notes()->count())->toBe(1);

    $reply = $ticket->comments()->replies()->first();

    expect($reply->isReply())->toBeTrue()
        ->and($reply->isNote())->toBeFalse()
        ->and($reply->isSystem())->toBeFalse()
        ->and($reply->isInternal())->toBeFalse();
});

it('exposes every relation the traits promise', function () {
    $user = readmeUser();
    $ticket = readmeTicket($user);

    HelpDesk::addComment($ticket, $user, 'Public reply.');
    HelpDesk::addWatcher($ticket, $user);
    HelpDesk::assignTicket($ticket, $user);
    HelpDesk::addOperator($ticket->department, $user, 'manager');

    expect($user->helpDeskTickets)->toHaveCount(1)
        ->and($user->helpDeskComments)->toHaveCount(1)
        ->and($user->helpDeskWatching)->toHaveCount(1)
        ->and($user->helpDeskAssignedTickets)->toHaveCount(1)
        ->and($user->helpDeskDepartments)->toHaveCount(1)
        ->and($user->helpDeskDepartments->first()->pivot->role)->toBe('manager');
});

it('records history the README says it records', function () {
    // The suite boots with help-desk.register_default_listeners off, and the
    // provider reads that at boot, so flipping the config here would be too
    // late. Subscribe the one listener this example is about.
    Event::subscribe(LogTicketHistory::class);

    $user = readmeUser();
    $ticket = readmeTicket($user);

    HelpDesk::changeStatus($ticket, TicketStatus::InProgress, $user);

    $entry = $ticket->history()
        ->where('action', HistoryAction::StatusChanged)
        ->first();

    expect($entry)->not->toBeNull()
        ->and($entry->field)->toBe('status')
        ->and($entry->new_value)->toBe(TicketStatus::InProgress->value)
        ->and($entry->performer->is($user))->toBeTrue();
});

it('guards a history performer the application cannot resolve', function () {
    $entry = (object) ['performer_type' => 'app-b-user'];

    expect(Ticket::morphIsResolvable($entry->performer_type))->toBeFalse();
});

it('throws the documented exceptions', function () {
    expect(fn () => HelpDesk::findTicketByReference('HD-99999'))
        ->toThrow(TicketNotFoundException::class);

    expect(fn () => HelpDesk::findTicketByUuid('not-a-uuid'))
        ->toThrow(TicketNotFoundException::class);

    $ticket = readmeTicket(readmeUser());
    HelpDesk::closeTicket($ticket);

    expect(fn () => HelpDesk::changeStatus($ticket->fresh(), TicketStatus::Resolved))
        ->toThrow(InvalidStatusTransitionException::class);

    expect(TicketStatus::Closed->canTransitionTo(TicketStatus::Resolved))->toBeFalse()
        ->and(TicketStatus::Closed->canTransitionTo(TicketStatus::Open))->toBeTrue();
});

it('exposes the attachment service and accessors', function () {
    Storage::fake('local');

    $user = readmeUser();
    $ticket = readmeTicket($user);
    $service = HelpDesk::attachments();

    expect($service->isAllowedExtension('pdf'))->toBeTrue()
        ->and($service->isAllowedExtension('exe'))->toBeFalse()
        ->and($service->isWithinSizeLimit(100))->toBeTrue()
        ->and($service->isWithinSizeLimit(999999))->toBeFalse();

    $attachment = $service->store($ticket, UploadedFile::fake()->create('scan.pdf', 1500), $user);

    expect($attachment->getFileSizeForHumans())->toContain('MB')
        ->and($attachment->getUrl())->toBeString()
        ->and($attachment->uploader_name)->toBe('Ada Lovelace')
        ->and($attachment->comment_id)->toBeNull();

    // The four-argument form both the README and the Boost guidelines show.
    $comment = HelpDesk::addComment($ticket, $user, 'See attached.');
    $tied = $service->store($ticket, UploadedFile::fake()->create('note.pdf', 10), $user, $comment);

    expect($tied->comment_id)->toBe($comment->id)
        ->and($comment->attachments()->count())->toBe(1);

    expect($service->delete($attachment))->toBeTrue();
});

it('updates a department and removes an operator', function () {
    $user = readmeUser();
    $department = HelpDesk::createDepartment(['name' => 'Technical Support']);

    HelpDesk::addOperator($department, $user, 'operator');
    expect($user->helpDeskDepartments()->count())->toBe(1);

    HelpDesk::removeOperator($department, $user);
    expect($user->helpDeskDepartments()->count())->toBe(0);

    HelpDesk::updateDepartment($department, ['name' => 'Support', 'is_active' => false]);

    expect($department->fresh()->name)->toBe('Support')
        ->and($department->fresh()->is_active)->toBeFalse();
});
