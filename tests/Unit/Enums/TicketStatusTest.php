<?php

use JeffersonGoncalves\HelpDesk\Enums\TicketStatus;
use JeffersonGoncalves\HelpDesk\Tests\TestCase;

uses(TestCase::class);

it('has all statuses', function () {
    expect(TicketStatus::cases())->toHaveCount(6);
});

it('has correct values', function () {
    expect(TicketStatus::Open->value)->toBe('open')
        ->and(TicketStatus::Pending->value)->toBe('pending')
        ->and(TicketStatus::InProgress->value)->toBe('in_progress')
        ->and(TicketStatus::OnHold->value)->toBe('on_hold')
        ->and(TicketStatus::Resolved->value)->toBe('resolved')
        ->and(TicketStatus::Closed->value)->toBe('closed');
});

it('allows open to transition to most statuses', function () {
    $allowed = TicketStatus::Open->allowedTransitions();

    expect($allowed)
        ->toContain(TicketStatus::Pending)
        ->toContain(TicketStatus::InProgress)
        ->toContain(TicketStatus::OnHold)
        ->toContain(TicketStatus::Resolved)
        ->toContain(TicketStatus::Closed);
});

it('allows closed to only transition to open', function () {
    $allowed = TicketStatus::Closed->allowedTransitions();

    expect($allowed)
        ->toHaveCount(1)
        ->toContain(TicketStatus::Open);
});

it('returns correct boolean for canTransitionTo', function () {
    expect(TicketStatus::Open->canTransitionTo(TicketStatus::Closed))->toBeTrue()
        ->and(TicketStatus::Closed->canTransitionTo(TicketStatus::Pending))->toBeFalse();
});

it('has limited transitions for resolved status', function () {
    $allowed = TicketStatus::Resolved->allowedTransitions();

    expect($allowed)
        ->toHaveCount(2)
        ->toContain(TicketStatus::Open)
        ->toContain(TicketStatus::Closed);
});
