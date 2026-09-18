<?php

use Illuminate\Support\Carbon;
use JeffersonGoncalves\HelpDesk\Enums\CommentType;
use JeffersonGoncalves\HelpDesk\Enums\TicketPriority;
use JeffersonGoncalves\HelpDesk\Enums\TicketStatus;
use JeffersonGoncalves\HelpDesk\Models\Department;
use JeffersonGoncalves\HelpDesk\Models\SlaPolicy;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Services\SlaService;
use JeffersonGoncalves\HelpDesk\Tests\TestUser;

afterEach(function () {
    Carbon::setTestNow();
});

beforeEach(function () {
    $this->service = app(SlaService::class);

    $this->user = TestUser::create(['name' => 'Jane Requester', 'email' => 'jane@example.com']);

    $this->department = Department::create([
        'name' => 'Support',
        'slug' => 'support',
        'is_active' => true,
    ]);
});

function makeSlaTicket(array $overrides = []): Ticket
{
    return Ticket::create(array_merge([
        'department_id' => test()->department->id,
        'user_type' => test()->user->getMorphClass(),
        'user_id' => test()->user->id,
        'title' => 'Test Ticket',
        'description' => 'Test description',
        'priority' => TicketPriority::High,
    ], $overrides));
}

it('leaves due dates null when no policy matches', function () {
    $ticket = makeSlaTicket();

    $this->service->applyPolicy($ticket);

    expect($ticket->sla_policy_id)->toBeNull()
        ->and($ticket->sla_first_response_due_at)->toBeNull()
        ->and($ticket->sla_resolution_due_at)->toBeNull();
});

it('resolves the most specific matching policy', function () {
    $generic = SlaPolicy::create(['first_response_minutes' => 999, 'resolution_minutes' => 999, 'is_active' => true]);
    $priorityOnly = SlaPolicy::create(['priority' => 'high', 'first_response_minutes' => 888, 'resolution_minutes' => 888, 'is_active' => true]);
    $departmentOnly = SlaPolicy::create(['department_id' => $this->department->id, 'first_response_minutes' => 777, 'resolution_minutes' => 777, 'is_active' => true]);
    $specific = SlaPolicy::create(['department_id' => $this->department->id, 'priority' => 'high', 'first_response_minutes' => 30, 'resolution_minutes' => 240, 'is_active' => true]);

    $ticket = makeSlaTicket();

    $this->service->applyPolicy($ticket);

    expect($ticket->sla_policy_id)->toBe($specific->id)
        ->and($ticket->sla_first_response_due_at)->toEqual($ticket->created_at->copy()->addMinutes(30));

    // Removing the specific policy falls back department-only, then generic.
    $specific->update(['is_active' => false]);
    $ticket2 = makeSlaTicket();
    $this->service->applyPolicy($ticket2);
    expect($ticket2->sla_policy_id)->toBe($departmentOnly->id);

    $departmentOnly->update(['is_active' => false]);
    $ticket3 = makeSlaTicket();
    $this->service->applyPolicy($ticket3);
    expect($ticket3->sla_policy_id)->toBe($priorityOnly->id);

    $priorityOnly->update(['is_active' => false]);
    $ticket4 = makeSlaTicket();
    $this->service->applyPolicy($ticket4);
    expect($ticket4->sla_policy_id)->toBe($generic->id);
});

it('computes both due dates as a straight addition under a 24/7 policy', function () {
    SlaPolicy::create(['first_response_minutes' => 60, 'resolution_minutes' => 1440, 'business_hours' => null, 'is_active' => true]);

    $ticket = makeSlaTicket();

    $this->service->applyPolicy($ticket);

    expect($ticket->sla_first_response_due_at)->toEqual($ticket->created_at->copy()->addMinutes(60))
        ->and($ticket->sla_resolution_due_at)->toEqual($ticket->created_at->copy()->addMinutes(1440));
});

it('computes a due date inside the same business day without rolling over', function () {
    $monday = Carbon::parse('2026-01-01')->next(Carbon::MONDAY)->setTime(10, 0);
    Carbon::setTestNow($monday);

    SlaPolicy::create([
        'first_response_minutes' => 60,
        'resolution_minutes' => 60,
        'business_hours' => ['mon' => ['09:00', '17:00'], 'tue' => ['09:00', '17:00'], 'wed' => ['09:00', '17:00'], 'thu' => ['09:00', '17:00'], 'fri' => ['09:00', '17:00']],
        'is_active' => true,
    ]);

    $ticket = makeSlaTicket();

    $this->service->applyPolicy($ticket);

    expect($ticket->sla_first_response_due_at)->toEqual($monday->copy()->setTime(11, 0));
});

it('rolls a due date past closing time into the next open day', function () {
    $friday = Carbon::parse('2026-01-01')->next(Carbon::MONDAY)->addDays(4)->setTime(16, 0);
    Carbon::setTestNow($friday);

    $businessHours = ['mon' => ['09:00', '17:00'], 'tue' => ['09:00', '17:00'], 'wed' => ['09:00', '17:00'], 'thu' => ['09:00', '17:00'], 'fri' => ['09:00', '17:00']];

    SlaPolicy::create([
        'first_response_minutes' => 120, // only 60 left before Friday close
        'resolution_minutes' => 120,
        'business_hours' => $businessHours,
        'is_active' => true,
    ]);

    $ticket = makeSlaTicket();

    $this->service->applyPolicy($ticket);

    $nextMonday = $friday->copy()->addDays(3)->setTime(10, 0);
    expect($ticket->sla_first_response_due_at)->toEqual($nextMonday);
});

it('rolls a due date created on a closed day forward to the next open day', function () {
    $saturday = Carbon::parse('2026-01-01')->next(Carbon::MONDAY)->addDays(5)->setTime(12, 0);
    Carbon::setTestNow($saturday);

    SlaPolicy::create([
        'first_response_minutes' => 30,
        'resolution_minutes' => 30,
        'business_hours' => ['mon' => ['09:00', '17:00']],
        'is_active' => true,
    ]);

    $ticket = makeSlaTicket();

    $this->service->applyPolicy($ticket);

    $nextMonday = $saturday->copy()->addDays(2)->setTime(9, 30);
    expect($ticket->sla_first_response_due_at)->toEqual($nextMonday);
});

it('pauses the resolution clock when entering Pending', function () {
    $ticket = makeSlaTicket(['sla_resolution_due_at' => now()->addHours(4)]);

    $this->service->trackPause($ticket, TicketStatus::Open, TicketStatus::Pending);

    expect($ticket->sla_paused_at)->not->toBeNull();
});

it('resumes and pushes the resolution due date forward by the elapsed pause', function () {
    $dueAt = now()->addHours(4);
    $ticket = makeSlaTicket(['sla_resolution_due_at' => $dueAt]);

    $this->service->trackPause($ticket, TicketStatus::Open, TicketStatus::Pending);

    $this->travel(30)->minutes();

    $this->service->trackPause($ticket, TicketStatus::Pending, TicketStatus::InProgress);

    expect($ticket->sla_paused_at)->toBeNull()
        ->and($ticket->total_sla_paused_minutes)->toBe(30)
        // ->timestamp (whole seconds): the round trip through the database
        // drops sub-second precision, so comparing full Carbon equality
        // against the in-memory $dueAt would flag a spurious microsecond
        // mismatch that has nothing to do with the 30-minute push.
        ->and($ticket->sla_resolution_due_at->timestamp)->toBe($dueAt->copy()->addMinutes(30)->timestamp);
});

it('does not reset or double-count the pause moving directly between Pending and OnHold', function () {
    $ticket = makeSlaTicket();

    $this->service->trackPause($ticket, TicketStatus::Open, TicketStatus::Pending);
    $pausedAt = $ticket->sla_paused_at;

    $this->travel(10)->minutes();

    $this->service->trackPause($ticket, TicketStatus::Pending, TicketStatus::OnHold);

    expect($ticket->sla_paused_at->timestamp)->toBe($pausedAt->timestamp)
        ->and($ticket->total_sla_paused_minutes)->toBe(0);
});

it('resumes without erroring when there is no resolution due date to push', function () {
    $ticket = makeSlaTicket();

    $this->service->trackPause($ticket, TicketStatus::Open, TicketStatus::Pending);

    $this->travel(15)->minutes();

    $this->service->trackPause($ticket, TicketStatus::Pending, TicketStatus::InProgress);

    expect($ticket->sla_paused_at)->toBeNull()
        ->and($ticket->total_sla_paused_minutes)->toBe(15)
        ->and($ticket->sla_resolution_due_at)->toBeNull();
});

it('never adjusts sla_first_response_due_at when pausing or resuming', function () {
    $firstResponseDue = now()->addHour();
    $ticket = makeSlaTicket(['sla_first_response_due_at' => $firstResponseDue]);

    $this->service->trackPause($ticket, TicketStatus::Open, TicketStatus::Pending);

    $this->travel(20)->minutes();

    $this->service->trackPause($ticket, TicketStatus::Pending, TicketStatus::InProgress);

    expect($ticket->sla_first_response_due_at->timestamp)->toBe($firstResponseDue->timestamp);
});

it('records first_response_at on the first reply from someone other than the requester', function () {
    $operator = TestUser::create(['name' => 'Agent Smith', 'email' => 'agent@example.com']);
    $ticket = makeSlaTicket();

    $comment = $ticket->comments()->create([
        'author_type' => $operator->getMorphClass(),
        'author_id' => $operator->id,
        'body' => 'How can I help?',
        'type' => CommentType::Reply,
    ]);

    $this->service->recordFirstResponse($ticket, $comment);

    expect($ticket->first_response_at)->not->toBeNull();
});

it('does not overwrite first_response_at once already set', function () {
    $operator = TestUser::create(['name' => 'Agent Smith', 'email' => 'agent@example.com']);
    $ticket = makeSlaTicket(['first_response_at' => now()->subHour()]);
    $original = $ticket->first_response_at;

    $comment = $ticket->comments()->create([
        'author_type' => $operator->getMorphClass(),
        'author_id' => $operator->id,
        'body' => 'Second reply',
        'type' => CommentType::Reply,
    ]);

    $this->service->recordFirstResponse($ticket, $comment);

    expect($ticket->first_response_at->equalTo($original))->toBeTrue();
});

it('does not record first_response_at for a reply from the requester', function () {
    $ticket = makeSlaTicket();

    $comment = $ticket->comments()->create([
        'author_type' => $this->user->getMorphClass(),
        'author_id' => $this->user->id,
        'body' => 'Any update?',
        'type' => CommentType::Reply,
    ]);

    $this->service->recordFirstResponse($ticket, $comment);

    expect($ticket->first_response_at)->toBeNull();
});

it('does not record first_response_at for an internal note', function () {
    $operator = TestUser::create(['name' => 'Agent Smith', 'email' => 'agent@example.com']);
    $ticket = makeSlaTicket();

    $comment = $ticket->comments()->create([
        'author_type' => $operator->getMorphClass(),
        'author_id' => $operator->id,
        'body' => 'Internal note',
        'type' => CommentType::Note,
        'is_internal' => true,
    ]);

    $this->service->recordFirstResponse($ticket, $comment);

    expect($ticket->first_response_at)->toBeNull();
});
