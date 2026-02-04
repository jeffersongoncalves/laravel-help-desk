<?php

namespace JeffersonGoncalves\HelpDesk\Tests\Unit\Enums;

use JeffersonGoncalves\HelpDesk\Enums\TicketPriority;
use PHPUnit\Framework\TestCase;

class TicketPriorityTest extends TestCase
{
    public function test_all_priorities_exist(): void
    {
        $this->assertCount(4, TicketPriority::cases());
    }

    public function test_priority_values(): void
    {
        $this->assertEquals('low', TicketPriority::Low->value);
        $this->assertEquals('medium', TicketPriority::Medium->value);
        $this->assertEquals('high', TicketPriority::High->value);
        $this->assertEquals('urgent', TicketPriority::Urgent->value);
    }

    public function test_numeric_values_are_ascending(): void
    {
        $this->assertEquals(1, TicketPriority::Low->numericValue());
        $this->assertEquals(2, TicketPriority::Medium->numericValue());
        $this->assertEquals(3, TicketPriority::High->numericValue());
        $this->assertEquals(4, TicketPriority::Urgent->numericValue());
    }
}
