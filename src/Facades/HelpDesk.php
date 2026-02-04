<?php

namespace JeffersonGoncalves\HelpDesk\Facades;

use Illuminate\Support\Facades\Facade;
use JeffersonGoncalves\HelpDesk\HelpDeskManager;

/**
 * @method static \JeffersonGoncalves\HelpDesk\Models\Ticket createTicket(array $data, \Illuminate\Database\Eloquent\Model $user)
 * @method static \JeffersonGoncalves\HelpDesk\Models\Ticket updateTicket(\JeffersonGoncalves\HelpDesk\Models\Ticket $ticket, array $data, ?\Illuminate\Database\Eloquent\Model $performer = null)
 * @method static \JeffersonGoncalves\HelpDesk\Models\Ticket changeStatus(\JeffersonGoncalves\HelpDesk\Models\Ticket $ticket, \JeffersonGoncalves\HelpDesk\Enums\TicketStatus $status, ?\Illuminate\Database\Eloquent\Model $performer = null)
 * @method static \JeffersonGoncalves\HelpDesk\Models\Ticket assignTicket(\JeffersonGoncalves\HelpDesk\Models\Ticket $ticket, \Illuminate\Database\Eloquent\Model $operator, ?\Illuminate\Database\Eloquent\Model $assignedBy = null)
 * @method static \JeffersonGoncalves\HelpDesk\Models\Ticket closeTicket(\JeffersonGoncalves\HelpDesk\Models\Ticket $ticket, ?\Illuminate\Database\Eloquent\Model $performer = null)
 * @method static \JeffersonGoncalves\HelpDesk\Models\Ticket reopenTicket(\JeffersonGoncalves\HelpDesk\Models\Ticket $ticket, ?\Illuminate\Database\Eloquent\Model $performer = null)
 * @method static \JeffersonGoncalves\HelpDesk\Models\Ticket findTicketByUuid(string $uuid)
 * @method static \JeffersonGoncalves\HelpDesk\Models\Ticket findTicketByReference(string $reference)
 * @method static \JeffersonGoncalves\HelpDesk\Models\TicketComment addComment(\JeffersonGoncalves\HelpDesk\Models\Ticket $ticket, \Illuminate\Database\Eloquent\Model $author, string $body, array $options = [])
 * @method static \JeffersonGoncalves\HelpDesk\Models\TicketComment addNote(\JeffersonGoncalves\HelpDesk\Models\Ticket $ticket, \Illuminate\Database\Eloquent\Model $author, string $body, array $options = [])
 * @method static \JeffersonGoncalves\HelpDesk\Models\Department createDepartment(array $data)
 * @method static \JeffersonGoncalves\HelpDesk\Services\TicketService tickets()
 * @method static \JeffersonGoncalves\HelpDesk\Services\CommentService comments()
 * @method static \JeffersonGoncalves\HelpDesk\Services\DepartmentService departments()
 * @method static \JeffersonGoncalves\HelpDesk\Services\AttachmentService attachments()
 *
 * @see \JeffersonGoncalves\HelpDesk\HelpDeskManager
 */
class HelpDesk extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return HelpDeskManager::class;
    }
}
