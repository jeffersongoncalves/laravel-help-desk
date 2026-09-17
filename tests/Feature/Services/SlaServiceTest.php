<?php

use Illuminate\Support\Carbon;
use JeffersonGoncalves\HelpDesk\Enums\TicketPriority;
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
