<?php

namespace JeffersonGoncalves\HelpDesk\Tests\Unit\Enums;

use JeffersonGoncalves\HelpDesk\Enums\TicketStatus;
use PHPUnit\Framework\TestCase;

class TicketStatusTest extends TestCase
{
    public function test_all_statuses_exist(): void
    {
        $this->assertCount(6, TicketStatus::cases());
    }

    public function test_status_values(): void
    {
        $this->assertEquals('open', TicketStatus::Open->value);
        $this->assertEquals('pending', TicketStatus::Pending->value);
        $this->assertEquals('in_progress', TicketStatus::InProgress->value);
        $this->assertEquals('on_hold', TicketStatus::OnHold->value);
        $this->assertEquals('resolved', TicketStatus::Resolved->value);
        $this->assertEquals('closed', TicketStatus::Closed->value);
    }

    public function test_open_can_transition_to_most_statuses(): void
    {
        $allowed = TicketStatus::Open->allowedTransitions();

        $this->assertContains(TicketStatus::Pending, $allowed);
        $this->assertContains(TicketStatus::InProgress, $allowed);
        $this->assertContains(TicketStatus::OnHold, $allowed);
        $this->assertContains(TicketStatus::Resolved, $allowed);
        $this->assertContains(TicketStatus::Closed, $allowed);
    }

    public function test_closed_can_only_transition_to_open(): void
    {
        $allowed = TicketStatus::Closed->allowedTransitions();

        $this->assertContains(TicketStatus::Open, $allowed);
        $this->assertCount(1, $allowed);
    }

    public function test_can_transition_to_returns_correct_boolean(): void
    {
        $this->assertTrue(TicketStatus::Open->canTransitionTo(TicketStatus::Closed));
        $this->assertFalse(TicketStatus::Closed->canTransitionTo(TicketStatus::Pending));
    }

    public function test_resolved_has_limited_transitions(): void
    {
        $allowed = TicketStatus::Resolved->allowedTransitions();

        $this->assertContains(TicketStatus::Open, $allowed);
        $this->assertContains(TicketStatus::Closed, $allowed);
        $this->assertCount(2, $allowed);
    }
}
