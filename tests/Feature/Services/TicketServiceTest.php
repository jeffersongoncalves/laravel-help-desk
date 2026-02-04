<?php

namespace JeffersonGoncalves\HelpDesk\Tests\Feature\Services;

use Illuminate\Support\Facades\Event;
use JeffersonGoncalves\HelpDesk\Enums\TicketStatus;
use JeffersonGoncalves\HelpDesk\Events\TicketAssigned;
use JeffersonGoncalves\HelpDesk\Events\TicketClosed;
use JeffersonGoncalves\HelpDesk\Events\TicketCreated;
use JeffersonGoncalves\HelpDesk\Events\TicketPriorityChanged;
use JeffersonGoncalves\HelpDesk\Events\TicketReopened;
use JeffersonGoncalves\HelpDesk\Events\TicketStatusChanged;
use JeffersonGoncalves\HelpDesk\Events\TicketUpdated;
use JeffersonGoncalves\HelpDesk\Exceptions\InvalidStatusTransitionException;
use JeffersonGoncalves\HelpDesk\Exceptions\TicketNotFoundException;
use JeffersonGoncalves\HelpDesk\Models\Department;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Services\TicketService;
use JeffersonGoncalves\HelpDesk\Tests\TestCase;
use JeffersonGoncalves\HelpDesk\Tests\TestUser;

class TicketServiceTest extends TestCase
{
    private TicketService $service;

    private TestUser $user;

    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TicketService::class);

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

    private function createTicket(array $overrides = []): Ticket
    {
        return Ticket::create(array_merge([
            'department_id' => $this->department->id,
            'user_type' => $this->user->getMorphClass(),
            'user_id' => $this->user->id,
            'title' => 'Test Ticket',
            'description' => 'Test description',
        ], $overrides));
    }

    public function test_create_ticket(): void
    {
        Event::fake([TicketCreated::class]);

        $ticket = $this->service->create([
            'title' => 'Test Ticket',
            'description' => 'Test description',
            'department_id' => $this->department->id,
        ], $this->user);

        $this->assertInstanceOf(Ticket::class, $ticket);
        $this->assertEquals('Test Ticket', $ticket->title);
        $this->assertEquals($this->user->id, $ticket->user_id);
        $this->assertNotEmpty($ticket->uuid);
        $this->assertNotEmpty($ticket->reference_number);

        Event::assertDispatched(TicketCreated::class);
    }

    public function test_change_status(): void
    {
        $ticket = $this->createTicket();

        Event::fake([TicketStatusChanged::class, TicketUpdated::class]);

        $updated = $this->service->changeStatus($ticket, TicketStatus::InProgress);

        $this->assertEquals(TicketStatus::InProgress, $updated->status);
        Event::assertDispatched(TicketStatusChanged::class);
    }

    public function test_invalid_status_transition_throws_exception(): void
    {
        $ticket = $this->createTicket(['status' => TicketStatus::Closed]);

        $this->expectException(InvalidStatusTransitionException::class);

        $this->service->changeStatus($ticket, TicketStatus::InProgress);
    }

    public function test_close_ticket(): void
    {
        $ticket = $this->createTicket();

        Event::fake([TicketClosed::class, TicketStatusChanged::class, TicketUpdated::class]);

        $closed = $this->service->close($ticket);

        $this->assertEquals(TicketStatus::Closed, $closed->status);
        Event::assertDispatched(TicketClosed::class);
    }

    public function test_reopen_ticket(): void
    {
        $ticket = $this->createTicket(['status' => TicketStatus::Closed]);

        Event::fake([TicketReopened::class, TicketStatusChanged::class, TicketUpdated::class]);

        $reopened = $this->service->reopen($ticket);

        $this->assertEquals(TicketStatus::Open, $reopened->status);
        Event::assertDispatched(TicketReopened::class);
    }

    public function test_assign_ticket(): void
    {
        $operator = TestUser::create([
            'name' => 'Operator',
            'email' => 'operator@example.com',
        ]);

        $ticket = $this->createTicket();

        Event::fake([TicketAssigned::class]);

        $assigned = $this->service->assign($ticket, $operator);

        $this->assertEquals($operator->id, $assigned->assigned_to_id);
        Event::assertDispatched(TicketAssigned::class);
    }

    public function test_find_by_uuid(): void
    {
        $ticket = $this->createTicket();

        $found = $this->service->findByUuid($ticket->uuid);

        $this->assertEquals($ticket->id, $found->id);
    }

    public function test_find_by_uuid_throws_when_not_found(): void
    {
        $this->expectException(TicketNotFoundException::class);

        $this->service->findByUuid('nonexistent-uuid');
    }

    public function test_find_by_reference(): void
    {
        $ticket = $this->createTicket();

        $found = $this->service->findByReference($ticket->reference_number);

        $this->assertEquals($ticket->id, $found->id);
    }

    public function test_add_and_remove_watcher(): void
    {
        $ticket = $this->createTicket();

        $watcher = TestUser::create([
            'name' => 'Watcher',
            'email' => 'watcher@example.com',
        ]);

        $this->service->addWatcher($ticket, $watcher);
        $this->assertCount(1, $ticket->fresh()->watchers);

        $this->service->removeWatcher($ticket, $watcher);
        $this->assertCount(0, $ticket->fresh()->watchers);
    }
}
