<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use JeffersonGoncalves\HelpDesk\Enums\TicketStatus;
use JeffersonGoncalves\HelpDesk\Events\TicketFeedbackSubmitted;
use JeffersonGoncalves\HelpDesk\Exceptions\TicketFeedbackNotAllowedException;
use JeffersonGoncalves\HelpDesk\Models\Department;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Services\FeedbackService;
use JeffersonGoncalves\HelpDesk\Tests\TestUser;

afterEach(function () {
    Carbon::setTestNow();
});

beforeEach(function () {
    $this->service = app(FeedbackService::class);

    $this->user = TestUser::create([
        'name' => 'Jane Requester',
        'email' => 'jane@example.com',
    ]);

    $this->department = Department::create([
        'name' => 'Support',
        'slug' => 'support',
        'is_active' => true,
    ]);
});

function makeFeedbackTicket(array $overrides = []): Ticket
{
    return Ticket::create(array_merge([
        'department_id' => test()->department->id,
        'user_type' => test()->user->getMorphClass(),
        'user_id' => test()->user->id,
        'title' => 'Test Ticket',
        'description' => 'Test description',
    ], $overrides));
}

it('accepts feedback on a resolved ticket', function () {
    $ticket = makeFeedbackTicket(['status' => TicketStatus::Resolved]);

    Event::fake([TicketFeedbackSubmitted::class]);

    $feedback = $this->service->submit($ticket, $this->user, 5, 'Great support!');

    expect($feedback->rating)->toBe(5)
        ->and($feedback->comment)->toBe('Great support!')
        ->and($feedback->submitted_by_type)->toBe($this->user->getMorphClass())
        ->and($feedback->submitted_by_id)->toBe($this->user->id)
        ->and($feedback->metadata['submitter']['name'])->toBe('Jane Requester');

    Event::assertDispatched(TicketFeedbackSubmitted::class, function (TicketFeedbackSubmitted $event) use ($ticket, $feedback) {
        return $event->ticket->is($ticket) && $event->feedback->is($feedback);
    });
});

it('accepts feedback on a closed ticket', function () {
    $ticket = makeFeedbackTicket(['status' => TicketStatus::Closed, 'closed_at' => now()]);

    $feedback = $this->service->submit($ticket, $this->user, 4);

    expect($feedback->rating)->toBe(4);
});

it('rejects feedback on a ticket that is neither closed nor resolved', function () {
    $ticket = makeFeedbackTicket(['status' => TicketStatus::Open]);

    Event::fake([TicketFeedbackSubmitted::class]);

    expect(fn () => $this->service->submit($ticket, $this->user, 5))
        ->toThrow(TicketFeedbackNotAllowedException::class);

    Event::assertNotDispatched(TicketFeedbackSubmitted::class);
});

it('rejects a second submission on the same ticket', function () {
    $ticket = makeFeedbackTicket(['status' => TicketStatus::Resolved]);

    $this->service->submit($ticket, $this->user, 5);

    expect(fn () => $this->service->submit($ticket->fresh(), $this->user, 3))
        ->toThrow(TicketFeedbackNotAllowedException::class);
});

it('rejects feedback submitted after the configured window has elapsed', function () {
    config(['help-desk.feedback.window_days' => 14]);

    $ticket = makeFeedbackTicket(['status' => TicketStatus::Closed, 'closed_at' => now()]);

    Carbon::setTestNow(now()->addDays(15));

    expect(fn () => $this->service->submit($ticket, $this->user, 5))
        ->toThrow(TicketFeedbackNotAllowedException::class);
});

it('accepts feedback still inside the configured window', function () {
    config(['help-desk.feedback.window_days' => 14]);

    $ticket = makeFeedbackTicket(['status' => TicketStatus::Closed, 'closed_at' => now()]);

    Carbon::setTestNow(now()->addDays(13));

    $feedback = $this->service->submit($ticket, $this->user, 5);

    expect($feedback->rating)->toBe(5);
});

it('rejects a rating outside 1 to 5 before writing anything', function (int $rating) {
    $ticket = makeFeedbackTicket(['status' => TicketStatus::Resolved]);

    Event::fake([TicketFeedbackSubmitted::class]);

    expect(fn () => $this->service->submit($ticket, $this->user, $rating))
        ->toThrow(TicketFeedbackNotAllowedException::class);

    expect($ticket->fresh()->feedback)->toBeNull();
    Event::assertNotDispatched(TicketFeedbackSubmitted::class);
})->with([0, 6, -1]);

it('exposes canReceiveFeedback per status', function () {
    expect(makeFeedbackTicket(['status' => TicketStatus::Open])->canReceiveFeedback())->toBeFalse()
        ->and(makeFeedbackTicket(['status' => TicketStatus::Resolved])->canReceiveFeedback())->toBeTrue()
        ->and(makeFeedbackTicket(['status' => TicketStatus::Closed, 'closed_at' => now()])->canReceiveFeedback())->toBeTrue();
});
