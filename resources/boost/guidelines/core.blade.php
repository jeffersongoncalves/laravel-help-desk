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
- **Never read a polymorphic relation directly.** See "Reading people" below

### Reading people (important)

Five models point at a person through a morph: `Ticket::user`, `TicketComment::author`,
`TicketAttachment::uploadedBy`, `TicketHistory::performer`, `TicketWatcher::watcher`.

Reading those relations directly is **fatal**, not null, when the stored morph type names
a class this application does not have — Eloquent instantiates the stored class name and
PHP raises `Class "..." not found`. That happens whenever several applications share one
help desk database.

Each model therefore stores an identity snapshot in its `metadata` column and exposes
accessors that prefer the live model and fall back to the copy:

| Model | Use instead of the relation |
|-------|------------------------------|
| `Ticket` | `requester_name`, `requester_email`, `requester()` |
| `TicketComment` | `author_name`, `author_email`, `resolvedAuthor()` |
| `TicketAttachment` | `uploader_name`, `uploader_email`, `resolvedUploadedBy()` |
| `TicketHistory` | `performer_name`, `performer_email`, `resolvedPerformer()` |
| `TicketWatcher` | `watcher_name`, `watcher_email`, `resolvedWatcher()` |

`Ticket::notifyRequester($notification)` follows the same rule: it uses the model when it
resolves and an on-demand mail notification to the snapshot address otherwise.

A user model whose display fields are named differently overrides `toHelpDeskSnapshot()`.

### Sharing one database across applications

- `help-desk.connection` routes every model **and migration** to another connection. Null
  keeps the application default
- The central application owns the schema; satellites set the connection but must **not**
  run the help desk migrations
- `help-desk.app.key` is stamped onto tickets as `app_key`; read it back with
  `$ticket->app_key` / `$ticket->app_name`, filter with `Ticket::forApp($key)`
- `help-desk.scope_to_app` adds a global scope restricting the application to its own
  tickets. Leave it off in the central application
- Applications with separate `users` tables need a distinct morph alias each
  (`Relation::enforceMorphMap`), or their user `#5` collide
- Attachments need a **shared disk**; `attachment_disk` defaults to `local`

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

### Attachments

Reach for `HelpDesk::attachments()` when the file is not going through
`addComment(['attachments' => ...])`.

@verbatim
<code-snippet name="Storing and reading an attachment" lang="php">
$service = HelpDesk::attachments();

// Validate first — the service does not enforce the limits for you
$service->isAllowedExtension($file->getClientOriginalExtension());
$service->isWithinSizeLimit($file->getSize() / 1024); // KB

$attachment = $service->store($ticket, $file, $user, $comment);

$attachment->getUrl();
$attachment->getTemporaryUrl(5);      // signed, needs a disk that supports it
$attachment->getFileSizeForHumans();
</code-snippet>
@endverbatim

### Exceptions

All extend `RuntimeException`.

- `TicketNotFoundException` — `findTicketByUuid()`, `findTicketByReference()`
- `InvalidStatusTransitionException` — `changeStatus()`, `closeTicket()`, `reopenTicket()`
- `UnauthorizedOperatorException` — provided for your authorization checks; the package
  never throws it
- `EmailProcessingException` — the inbound drivers. Not thrown at the caller:
  `ProcessInboundEmail` catches it and marks the `InboundEmail` row failed

### Email Integration

The package supports 5 inbound email drivers: IMAP, Mailgun, SendGrid, Resend, and Postmark. Webhook routes are registered at the prefix configured in `config('help-desk.webhooks.prefix')` (default: `help-desk/webhooks`).

### Disabling Default Listeners

Set `'register_default_listeners' => false` in config to handle events yourself.
