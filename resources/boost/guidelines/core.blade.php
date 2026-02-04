## Laravel Help Desk Package

The `jeffersongoncalves/laravel-help-desk` package provides a comprehensive help desk and ticket management system with email integration for Laravel applications.

### Package Namespace

All classes are under `JeffersonGoncalves\HelpDesk`.

### Architecture

- **Facade**: `JeffersonGoncalves\HelpDesk\Facades\HelpDesk` - Primary entry point for all operations
- **Services**: `TicketService`, `CommentService`, `DepartmentService`, `AttachmentService`, `InboundEmailService`
- **Models**: `Ticket`, `TicketComment`, `TicketAttachment`, `TicketHistory`, `TicketWatcher`, `Department`, `Category`, `CannedResponse`, `EmailChannel`, `InboundEmail`
- **Enums**: `TicketStatus`, `TicketPriority`, `HistoryAction`, `CommentType`
- **Traits**: `HasTickets` (for user models), `IsOperator` (for operator models, includes HasTickets)

### Key Conventions

- All database tables use the `help_desk_` prefix
- Tickets use polymorphic `user` and `assigned_to` relationships (morphTo)
- Ticket status transitions are validated via `TicketStatus::canTransitionTo()`
- The `Closed` status can only transition to `Open` (reopen)
- Tickets auto-generate a UUID and reference number (e.g., `HD-00001`) on creation
- Events are dispatched for all ticket lifecycle changes
- Configuration is in `config/help-desk.php`
- Translations are namespaced as `help-desk::` (e.g., `__('help-desk::statuses.open')`)

### User Model Setup

Users who create tickets must use the `HasTickets` trait. Operators who manage tickets must use the `IsOperator` trait (which includes `HasTickets`).

@verbatim
<code-snippet name="User model with HasTickets trait" lang="php">
use JeffersonGoncalves\HelpDesk\Concerns\HasTickets;

class User extends Authenticatable
{
    use HasTickets;
}
</code-snippet>
@endverbatim

@verbatim
<code-snippet name="Operator model with IsOperator trait" lang="php">
use JeffersonGoncalves\HelpDesk\Concerns\IsOperator;

class User extends Authenticatable
{
    use IsOperator; // Includes HasTickets
}
</code-snippet>
@endverbatim

### Creating and Managing Tickets

Always use the `HelpDesk` facade or inject the service classes directly.

@verbatim
<code-snippet name="Creating a ticket via facade" lang="php">
use JeffersonGoncalves\HelpDesk\Facades\HelpDesk;

$ticket = HelpDesk::createTicket([
    'title' => 'Cannot access my account',
    'description' => 'I get an error when trying to log in...',
    'department_id' => $department->id,
    'priority' => 'high',
], $user);
</code-snippet>
@endverbatim

@verbatim
<code-snippet name="Managing ticket status" lang="php">
use JeffersonGoncalves\HelpDesk\Enums\TicketStatus;
use JeffersonGoncalves\HelpDesk\Facades\HelpDesk;

HelpDesk::changeStatus($ticket, TicketStatus::InProgress);
HelpDesk::closeTicket($ticket);
HelpDesk::reopenTicket($ticket);
</code-snippet>
@endverbatim

### Status Transitions

| From | Allowed Transitions |
|------|-------------------|
| Open | Pending, InProgress, OnHold, Resolved, Closed |
| Pending | Open, InProgress, OnHold, Resolved, Closed |
| InProgress | Pending, OnHold, Resolved, Closed |
| OnHold | Open, Pending, InProgress, Resolved, Closed |
| Resolved | Open, Closed |
| Closed | Open (reopen only) |

### Events

The package dispatches these events: `TicketCreated`, `TicketUpdated`, `TicketStatusChanged`, `TicketPriorityChanged`, `TicketAssigned`, `TicketClosed`, `TicketReopened`, `TicketDeleted`, `CommentAdded`, `AttachmentAdded`, `AttachmentRemoved`, `InboundEmailReceived`, `InboundEmailProcessed`.

### Querying Tickets

@verbatim
<code-snippet name="Ticket query scopes" lang="php">
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Enums\TicketStatus;
use JeffersonGoncalves\HelpDesk\Enums\TicketPriority;

Ticket::open()->get();
Ticket::closed()->get();
Ticket::byStatus(TicketStatus::InProgress)->get();
Ticket::byPriority(TicketPriority::Urgent)->get();
Ticket::overdue()->get();
Ticket::unassigned()->get();
</code-snippet>
@endverbatim

### Email Integration

The package supports 5 inbound email drivers: IMAP, Mailgun, SendGrid, Resend, and Postmark. Webhook routes are registered at the prefix configured in `config('help-desk.webhooks.prefix')` (default: `help-desk/webhooks`).

### Disabling Default Listeners

Set `'register_default_listeners' => false` in config to handle events yourself.
