<?php

use Illuminate\Support\Facades\Event;
use JeffersonGoncalves\HelpDesk\Enums\TicketStatus;
use JeffersonGoncalves\HelpDesk\Events\TicketFeedbackSubmitted;
use JeffersonGoncalves\HelpDesk\Events\TicketReopened;
use JeffersonGoncalves\HelpDesk\Exceptions\InvalidStatusTransitionException;
use JeffersonGoncalves\HelpDesk\Listeners\AutoReopenOnLowFeedbackRating;
use JeffersonGoncalves\HelpDesk\Models\Department;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Tests\TestUser;

beforeEach(function () {
    $this->user = TestUser::create(['name' => 'Jane Requester', 'email' => 'jane@example.com']);

    $this->department = Department::create([
        'name' => 'Support',
        'slug' => 'support',
        'is_active' => true,
    ]);
});

function ticketWithFeedback(int $rating, array $ticketOverrides = []): Ticket
{
    $ticket = Ticket::create(array_merge([
        'department_id' => test()->department->id,
        'user_type' => test()->user->getMorphClass(),
        'user_id' => test()->user->id,
        'title' => 'Test Ticket',
        'description' => 'Test description',
        'status' => TicketStatus::Resolved,
    ], $ticketOverrides));

    $feedback = $ticket->feedback()->create([
        'rating' => $rating,
        'submitted_by_type' => test()->user->getMorphClass(),
        'submitted_by_id' => test()->user->id,
    ]);

    return $ticket->setRelation('feedback', $feedback);
}

function triggerAutoReopen(Ticket $ticket): void
{
    app(AutoReopenOnLowFeedbackRating::class)->handle(new TicketFeedbackSubmitted($ticket, $ticket->feedback));
}

it('does nothing when auto_reopen is disabled', function () {
    config(['help-desk.feedback.auto_reopen.enabled' => false]);

    $ticket = ticketWithFeedback(1);

    triggerAutoReopen($ticket);

    expect($ticket->fresh()->status)->toBe(TicketStatus::Resolved);
});

it('reopens the ticket and adds a system comment when the rating is at or below the threshold', function () {
    config([
        'help-desk.feedback.auto_reopen.enabled' => true,
        'help-desk.feedback.auto_reopen.rating_threshold' => 1,
        'help-desk.feedback.auto_reopen.comment' => 'Reopened: low rating.',
    ]);

    // Closed, not Resolved: TicketReopened only fires for a Closed -> Open
    // transition -- see TicketService::update().
    $ticket = ticketWithFeedback(1, ['status' => TicketStatus::Closed, 'closed_at' => now()]);

    Event::fake([TicketReopened::class]);

    triggerAutoReopen($ticket);

    $fresh = $ticket->fresh();

    expect($fresh->status)->toBe(TicketStatus::Open)
        ->and($fresh->comments)->toHaveCount(1)
        ->and($fresh->comments->first()->body)->toBe('Reopened: low rating.')
        ->and($fresh->comments->first()->isSystem())->toBeTrue();

    Event::assertDispatched(TicketReopened::class);
});

it('does not reopen when the rating is above the threshold', function () {
    config([
        'help-desk.feedback.auto_reopen.enabled' => true,
        'help-desk.feedback.auto_reopen.rating_threshold' => 1,
    ]);

    $ticket = ticketWithFeedback(3);

    triggerAutoReopen($ticket);

    expect($ticket->fresh()->status)->toBe(TicketStatus::Resolved)
        ->and($ticket->fresh()->comments)->toHaveCount(0);
});

it('does not swallow an invalid transition when reopening is disallowed', function () {
    config([
        'help-desk.feedback.auto_reopen.enabled' => true,
        'help-desk.ticket.allow_reopen' => false,
    ]);

    $ticket = ticketWithFeedback(1, ['status' => TicketStatus::Closed, 'closed_at' => now()]);

    expect(fn () => triggerAutoReopen($ticket))
        ->toThrow(InvalidStatusTransitionException::class);
});
