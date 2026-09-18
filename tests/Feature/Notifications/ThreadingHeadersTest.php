<?php

use Illuminate\Notifications\Messages\MailMessage;
use JeffersonGoncalves\HelpDesk\Enums\TicketStatus;
use JeffersonGoncalves\HelpDesk\Models\Department;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Models\TicketComment;
use JeffersonGoncalves\HelpDesk\Notifications\NewCommentNotification;
use JeffersonGoncalves\HelpDesk\Notifications\TicketAssignedNotification;
use JeffersonGoncalves\HelpDesk\Notifications\TicketClosedNotification;
use JeffersonGoncalves\HelpDesk\Notifications\TicketCreatedNotification;
use JeffersonGoncalves\HelpDesk\Notifications\TicketStatusChangedNotification;
use JeffersonGoncalves\HelpDesk\Tests\TestUser;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\IdentificationHeader;

beforeEach(function () {
    $requester = TestUser::create(['name' => 'Jane Requester', 'email' => 'jane@example.com']);
    $department = Department::create(['name' => 'Support', 'slug' => 'support', 'is_active' => true]);

    $this->ticket = Ticket::create([
        'department_id' => $department->id,
        'user_type' => $requester->getMorphClass(),
        'user_id' => $requester->id,
        'title' => 'Test Ticket',
        'description' => 'Test description',
        'status' => TicketStatus::Open,
    ]);
});

/**
 * Regression for a Symfony\Component\Mime\Exception\LogicException: the
 * "Message-ID" header must be built via addIdHeader(), not addTextHeader()
 * — Symfony rejects any other header class for that name.
 */
function assertMessageIdHeaderIsValid(MailMessage $mail): void
{
    $symfonyMessage = new Email;

    foreach ($mail->callbacks as $callback) {
        $callback($symfonyMessage);
    }

    expect($symfonyMessage->getHeaders()->get('Message-ID'))->toBeInstanceOf(IdentificationHeader::class);
}

it('builds a valid Message-ID header for TicketCreatedNotification', function () {
    assertMessageIdHeaderIsValid((new TicketCreatedNotification($this->ticket))->toMail(new TestUser));
});

it('builds a valid Message-ID header for TicketAssignedNotification', function () {
    assertMessageIdHeaderIsValid((new TicketAssignedNotification($this->ticket))->toMail(new TestUser));
});

it('builds a valid Message-ID header for TicketClosedNotification', function () {
    assertMessageIdHeaderIsValid((new TicketClosedNotification($this->ticket))->toMail(new TestUser));
});

it('builds a valid Message-ID header for TicketStatusChangedNotification', function () {
    $notification = new TicketStatusChangedNotification($this->ticket, TicketStatus::Open, TicketStatus::InProgress);

    assertMessageIdHeaderIsValid($notification->toMail(new TestUser));
});

it('builds a valid Message-ID header for NewCommentNotification', function () {
    $author = TestUser::create(['name' => 'Agent Smith', 'email' => 'agent@example.com']);

    $comment = TicketComment::create([
        'ticket_id' => $this->ticket->id,
        'body' => 'A reply.',
        'type' => 'reply',
        'is_internal' => false,
        'author_type' => $author->getMorphClass(),
        'author_id' => $author->id,
    ]);

    assertMessageIdHeaderIsValid((new NewCommentNotification($this->ticket, $comment))->toMail(new TestUser));
});
