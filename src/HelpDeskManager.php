<?php

namespace JeffersonGoncalves\HelpDesk;

use Illuminate\Database\Eloquent\Model;
use JeffersonGoncalves\HelpDesk\Enums\TicketStatus;
use JeffersonGoncalves\HelpDesk\Models\Department;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Models\TicketComment;
use JeffersonGoncalves\HelpDesk\Services\AttachmentService;
use JeffersonGoncalves\HelpDesk\Services\CommentService;
use JeffersonGoncalves\HelpDesk\Services\DepartmentService;
use JeffersonGoncalves\HelpDesk\Services\TicketService;

class HelpDeskManager
{
    public function __construct(
        protected TicketService $ticketService,
        protected CommentService $commentService,
        protected DepartmentService $departmentService,
        protected AttachmentService $attachmentService,
    ) {}

    public function createTicket(array $data, Model $user): Ticket
    {
        return $this->ticketService->create($data, $user);
    }

    public function updateTicket(Ticket $ticket, array $data, ?Model $performer = null): Ticket
    {
        return $this->ticketService->update($ticket, $data, $performer);
    }

    public function changeStatus(Ticket $ticket, TicketStatus $status, ?Model $performer = null): Ticket
    {
        return $this->ticketService->changeStatus($ticket, $status, $performer);
    }

    public function assignTicket(Ticket $ticket, Model $operator, ?Model $assignedBy = null): Ticket
    {
        return $this->ticketService->assign($ticket, $operator, $assignedBy);
    }

    public function unassignTicket(Ticket $ticket, ?Model $performer = null): Ticket
    {
        return $this->ticketService->unassign($ticket, $performer);
    }

    public function closeTicket(Ticket $ticket, ?Model $performer = null): Ticket
    {
        return $this->ticketService->close($ticket, $performer);
    }

    public function reopenTicket(Ticket $ticket, ?Model $performer = null): Ticket
    {
        return $this->ticketService->reopen($ticket, $performer);
    }

    public function deleteTicket(Ticket $ticket, ?Model $performer = null): bool
    {
        return $this->ticketService->delete($ticket, $performer);
    }

    public function findTicketByUuid(string $uuid): Ticket
    {
        return $this->ticketService->findByUuid($uuid);
    }

    public function findTicketByReference(string $reference): Ticket
    {
        return $this->ticketService->findByReference($reference);
    }

    public function addComment(Ticket $ticket, Model $author, string $body, array $options = []): TicketComment
    {
        return $this->commentService->addReply($ticket, $author, $body, $options);
    }

    public function addNote(Ticket $ticket, Model $author, string $body, array $options = []): TicketComment
    {
        return $this->commentService->addNote($ticket, $author, $body, $options);
    }

    public function addWatcher(Ticket $ticket, Model $watcher): void
    {
        $this->ticketService->addWatcher($ticket, $watcher);
    }

    public function removeWatcher(Ticket $ticket, Model $watcher): void
    {
        $this->ticketService->removeWatcher($ticket, $watcher);
    }

    public function createDepartment(array $data): Department
    {
        return $this->departmentService->create($data);
    }

    public function updateDepartment(Department $department, array $data): Department
    {
        return $this->departmentService->update($department, $data);
    }

    public function addOperator(Department $department, Model $operator, string $role = 'operator'): void
    {
        $this->departmentService->addOperator($department, $operator, $role);
    }

    public function removeOperator(Department $department, Model $operator): void
    {
        $this->departmentService->removeOperator($department, $operator);
    }

    public function tickets(): TicketService
    {
        return $this->ticketService;
    }

    public function comments(): CommentService
    {
        return $this->commentService;
    }

    public function departments(): DepartmentService
    {
        return $this->departmentService;
    }

    public function attachments(): AttachmentService
    {
        return $this->attachmentService;
    }
}
