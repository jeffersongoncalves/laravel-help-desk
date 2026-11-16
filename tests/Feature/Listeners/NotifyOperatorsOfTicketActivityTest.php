<?php

use Illuminate\Support\Facades\Notification;
use JeffersonGoncalves\HelpDesk\Enums\TicketStatus;
use JeffersonGoncalves\HelpDesk\Events\CommentAdded;
use JeffersonGoncalves\HelpDesk\Events\TicketClosed;
use JeffersonGoncalves\HelpDesk\Events\TicketCreated;
use JeffersonGoncalves\HelpDesk\Events\TicketStatusChanged;
use JeffersonGoncalves\HelpDesk\Listeners\NotifyOperatorsOfTicketActivity;
use JeffersonGoncalves\HelpDesk\Listeners\SendTicketClosedNotification;
use JeffersonGoncalves\HelpDesk\Listeners\SendTicketStatusChangedNotification;
use JeffersonGoncalves\HelpDesk\Models\Department;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Models\TicketComment;
use JeffersonGoncalves\HelpDesk\Models\TicketWatcher;
use JeffersonGoncalves\HelpDesk\Notifications\OperatorTicketActivityNotification;
use JeffersonGoncalves\HelpDesk\Notifications\TicketClosedNotification;
use JeffersonGoncalves\HelpDesk\Notifications\TicketStatusChangedNotification;
use JeffersonGoncalves\HelpDesk\Tests\TestUser;

beforeEach(function () {
    Notification::fake();

    $this->requester = TestUser::create(['name' => 'Jane', 'email' => 'jane@example.com']);
    $this->alice = TestUser::create(['name' => 'Alice', 'email' => 'alice@example.com']);
    $this->bob = TestUser::create(['name' => 'Bob', 'email' => 'bob@example.com']);
    $this->outsider = TestUser::create(['name' => 'Outsider', 'email' => 'out@example.com']);

    $this->department = Department::create(['name' => 'Support', 'slug' => 'support', 'is_active' => true]);
    $this->alice->helpDeskDepartments()->attach($this->department->id);
    $this->bob->helpDeskDepartments()->attach($this->department->id);

    $this->ticket = Ticket::create([
        'department_id' => $this->department->id,
        'user_type' => $this->requester->getMorphClass(),
        'user_id' => $this->requester->id,
        'title' => 'Printer on fire',
        'description' => 'Help',
        'status' => TicketStatus::Open,
    ]);
});

function commentBy(Ticket $ticket, TestUser $author, bool $internal = false): TicketComment
{
    return TicketComment::create([
        'ticket_id' => $ticket->id,
        'body' => 'Any news?',
        'type' => 'reply',
        'is_internal' => $internal,
        'author_type' => $author->getMorphClass(),
        'author_id' => $author->id,
    ]);
}

it('emails every department operator about a new unassigned ticket', function () {
    (new NotifyOperatorsOfTicketActivity)->handle(new TicketCreated($this->ticket));

    Notification::assertSentTo([$this->alice, $this->bob], OperatorTicketActivityNotification::class);
    Notification::assertNotSentTo([$this->requester, $this->outsider], OperatorTicketActivityNotification::class);
});

it('skips operators who turned email off for the department', function () {
    $this->bob->helpDeskDepartments()->updateExistingPivot($this->department->id, ['email_notifications' => false]);

    (new NotifyOperatorsOfTicketActivity)->handle(new TicketCreated($this->ticket));

    Notification::assertSentTo($this->alice, OperatorTicketActivityNotification::class);
    Notification::assertNotSentTo($this->bob, OperatorTicketActivityNotification::class);
});

it('emails only the assignee once the ticket is assigned', function () {
    $this->ticket->update(['assigned_to_type' => $this->alice->getMorphClass(), 'assigned_to_id' => $this->alice->id]);

    (new NotifyOperatorsOfTicketActivity)->handle(new CommentAdded($this->ticket, commentBy($this->ticket, $this->requester)));

    Notification::assertSentTo($this->alice, OperatorTicketActivityNotification::class,
        fn (OperatorTicketActivityNotification $n) => $n->comment !== null);
    Notification::assertNotSentTo($this->bob, OperatorTicketActivityNotification::class);
});

it('respects the assignee opting out', function () {
    $this->alice->helpDeskDepartments()->updateExistingPivot($this->department->id, ['email_notifications' => false]);
    $this->ticket->update(['assigned_to_type' => $this->alice->getMorphClass(), 'assigned_to_id' => $this->alice->id]);

    (new NotifyOperatorsOfTicketActivity)->handle(new TicketCreated($this->ticket));

    Notification::assertNothingSent();
});

it('ignores operator, internal and watcher-covered comments', function () {
    $listener = new NotifyOperatorsOfTicketActivity;

    $listener->handle(new CommentAdded($this->ticket, commentBy($this->ticket, $this->alice)));
    $listener->handle(new CommentAdded($this->ticket, commentBy($this->ticket, $this->requester, internal: true)));
    Notification::assertNothingSent();

    TicketWatcher::create([
        'ticket_id' => $this->ticket->id,
        'watcher_type' => $this->bob->getMorphClass(),
        'watcher_id' => $this->bob->id,
    ]);
    $listener->handle(new CommentAdded($this->ticket->fresh(), commentBy($this->ticket, $this->requester)));

    Notification::assertSentTo($this->alice, OperatorTicketActivityNotification::class);
    Notification::assertNotSentTo($this->bob, OperatorTicketActivityNotification::class);
});

it('still emails a watching assignee when comment notifications are off', function () {
    config()->set('help-desk.notifications.notify_on.comment_added', false);
    $this->ticket->update(['assigned_to_type' => $this->alice->getMorphClass(), 'assigned_to_id' => $this->alice->id]);
    TicketWatcher::create([
        'ticket_id' => $this->ticket->id,
        'watcher_type' => $this->alice->getMorphClass(),
        'watcher_id' => $this->alice->id,
    ]);

    (new NotifyOperatorsOfTicketActivity)->handle(new CommentAdded($this->ticket, commentBy($this->ticket, $this->requester)));

    Notification::assertSentTo($this->alice, OperatorTicketActivityNotification::class);
});

it('survives a soft-deleted department', function () {
    $this->department->delete();
    $ticket = $this->ticket->fresh();

    (new NotifyOperatorsOfTicketActivity)->handle(new TicketCreated($ticket));
    Notification::assertNothingSent();

    $ticket->update(['assigned_to_type' => $this->alice->getMorphClass(), 'assigned_to_id' => $this->alice->id]);
    (new NotifyOperatorsOfTicketActivity)->handle(new TicketCreated($ticket));
    Notification::assertSentTo($this->alice, OperatorTicketActivityNotification::class);
});

it('can be turned off in config', function () {
    config()->set('help-desk.notifications.notify_on.operator_new_ticket', false);

    (new NotifyOperatorsOfTicketActivity)->handle(new TicketCreated($this->ticket));

    Notification::assertNothingSent();
});

it('sends the closed email instead of the status email on close', function () {
    (new SendTicketStatusChangedNotification)->handle(
        new TicketStatusChanged($this->ticket, TicketStatus::Open, TicketStatus::Closed)
    );
    (new SendTicketClosedNotification)->handle(new TicketClosed($this->ticket));

    Notification::assertNotSentTo($this->requester, TicketStatusChangedNotification::class);
    Notification::assertSentTo($this->requester, TicketClosedNotification::class);
});

it('renders the operator email', function () {
    $mail = (new OperatorTicketActivityNotification($this->ticket))->toMail($this->alice);

    expect($mail->subject)->toContain($this->ticket->reference_number)
        ->and($mail->introLines[0])->toContain('Jane');
});
