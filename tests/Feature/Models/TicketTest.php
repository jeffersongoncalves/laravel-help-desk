<?php

namespace JeffersonGoncalves\HelpDesk\Tests\Feature\Models;

use JeffersonGoncalves\HelpDesk\Enums\TicketPriority;
use JeffersonGoncalves\HelpDesk\Enums\TicketStatus;
use JeffersonGoncalves\HelpDesk\Models\Department;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Tests\TestCase;
use JeffersonGoncalves\HelpDesk\Tests\TestUser;

class TicketTest extends TestCase
{
    private TestUser $user;

    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = TestUser::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->department = Department::create([
            'name' => 'Support',
            'slug' => 'support',
            'is_active' => true,
        ]);
    }

    public function test_ticket_auto_generates_uuid(): void
    {
        $ticket = Ticket::create([
            'department_id' => $this->department->id,
            'user_type' => $this->user->getMorphClass(),
            'user_id' => $this->user->id,
            'title' => 'Test Ticket',
            'description' => 'Test description',
        ]);

        $this->assertNotEmpty($ticket->uuid);
    }

    public function test_ticket_auto_generates_reference_number(): void
    {
        $ticket = Ticket::create([
            'department_id' => $this->department->id,
            'user_type' => $this->user->getMorphClass(),
            'user_id' => $this->user->id,
            'title' => 'Test Ticket',
            'description' => 'Test description',
        ]);

        $this->assertStringStartsWith('HD-', $ticket->reference_number);
    }

    public function test_ticket_defaults_to_open_status(): void
    {
        $ticket = Ticket::create([
            'department_id' => $this->department->id,
            'user_type' => $this->user->getMorphClass(),
            'user_id' => $this->user->id,
            'title' => 'Test Ticket',
            'description' => 'Test description',
        ]);

        $this->assertEquals(TicketStatus::Open, $ticket->status);
    }

    public function test_ticket_defaults_to_medium_priority(): void
    {
        $ticket = Ticket::create([
            'department_id' => $this->department->id,
            'user_type' => $this->user->getMorphClass(),
            'user_id' => $this->user->id,
            'title' => 'Test Ticket',
            'description' => 'Test description',
        ]);

        $this->assertEquals(TicketPriority::Medium, $ticket->priority);
    }

    public function test_ticket_belongs_to_department(): void
    {
        $ticket = Ticket::create([
            'department_id' => $this->department->id,
            'user_type' => $this->user->getMorphClass(),
            'user_id' => $this->user->id,
            'title' => 'Test Ticket',
            'description' => 'Test description',
        ]);

        $this->assertEquals($this->department->id, $ticket->department->id);
    }

    public function test_ticket_morph_to_user(): void
    {
        $ticket = Ticket::create([
            'department_id' => $this->department->id,
            'user_type' => $this->user->getMorphClass(),
            'user_id' => $this->user->id,
            'title' => 'Test Ticket',
            'description' => 'Test description',
        ]);

        $this->assertEquals($this->user->id, $ticket->user->id);
    }

    public function test_is_open_returns_true_for_open_ticket(): void
    {
        $ticket = Ticket::create([
            'department_id' => $this->department->id,
            'user_type' => $this->user->getMorphClass(),
            'user_id' => $this->user->id,
            'title' => 'Test Ticket',
            'description' => 'Test description',
            'status' => TicketStatus::Open,
        ]);

        $this->assertTrue($ticket->isOpen());
        $this->assertFalse($ticket->isClosed());
    }

    public function test_open_scope_excludes_closed_and_resolved(): void
    {
        Ticket::create([
            'department_id' => $this->department->id,
            'user_type' => $this->user->getMorphClass(),
            'user_id' => $this->user->id,
            'title' => 'Open Ticket',
            'description' => 'Description',
            'status' => TicketStatus::Open,
        ]);

        Ticket::create([
            'department_id' => $this->department->id,
            'user_type' => $this->user->getMorphClass(),
            'user_id' => $this->user->id,
            'title' => 'Closed Ticket',
            'description' => 'Description',
            'status' => TicketStatus::Closed,
        ]);

        $this->assertCount(1, Ticket::open()->get());
    }

    public function test_route_key_name_is_uuid(): void
    {
        $ticket = new Ticket();
        $this->assertEquals('uuid', $ticket->getRouteKeyName());
    }
}
