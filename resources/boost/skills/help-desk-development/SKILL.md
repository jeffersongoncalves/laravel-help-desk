---
name: help-desk-development
description: Build help desk and ticket management features using the jeffersongoncalves/laravel-help-desk package
---

# Help Desk Development

## When to use this skill

Use this skill when implementing help desk, ticket management, or customer support features in a Laravel application using the `jeffersongoncalves/laravel-help-desk` package. This includes creating tickets, managing ticket lifecycle, setting up departments, handling comments and attachments, configuring email integration, and building custom ticket workflows.

## Setup

### Install and configure

```bash
composer require jeffersongoncalves/laravel-help-desk
php artisan vendor:publish --tag=help-desk-config
php artisan vendor:publish --tag=help-desk-migrations
php artisan migrate
```

### Add traits to your User model

For regular users (ticket creators):

```php
use JeffersonGoncalves\HelpDesk\Concerns\HasTickets;

class User extends Authenticatable
{
    use HasTickets;
}
```

For operators/agents (ticket managers):

```php
use JeffersonGoncalves\HelpDesk\Concerns\IsOperator;

class User extends Authenticatable
{
    use IsOperator; // Includes HasTickets
}
```

### Create departments

```php
use JeffersonGoncalves\HelpDesk\Facades\HelpDesk;

$department = HelpDesk::createDepartment([
    'name' => 'Technical Support',
    'slug' => 'technical-support',
    'email' => 'support@example.com',
    'is_active' => true,
]);

HelpDesk::addOperator($department, $user, 'operator'); // 'operator', 'manager', or 'admin'
```

## Ticket Operations

### Creating tickets

```php
use JeffersonGoncalves\HelpDesk\Facades\HelpDesk;

$ticket = HelpDesk::createTicket([
    'title' => 'Cannot access my account',
    'description' => 'I get an error when trying to log in...',
    'department_id' => $department->id,
    'priority' => 'high',       // low, medium, high, urgent
    'category_id' => $category->id, // optional
], $user);

// Auto-generated fields:
// $ticket->uuid => "550e8400-e29b-41d4-a716-446655440000"
// $ticket->reference_number => "HD-00001"
// $ticket->status => TicketStatus::Open
```

### Finding tickets

```php
$ticket = HelpDesk::findTicketByReference('HD-00001');
$ticket = HelpDesk::findTicketByUuid('550e8400-...');

// These throw TicketNotFoundException if not found
```

### Status management

```php
use JeffersonGoncalves\HelpDesk\Enums\TicketStatus;

HelpDesk::changeStatus($ticket, TicketStatus::InProgress);
HelpDesk::closeTicket($ticket);
HelpDesk::reopenTicket($ticket);

// Status transitions are validated. Invalid transitions throw InvalidStatusTransitionException.
// Closed tickets can only transition to Open (reopen).
// Resolved tickets can only go to Open or Closed.
```

### Assignment

```php
HelpDesk::assignTicket($ticket, $operator);
HelpDesk::unassignTicket($ticket);
```

### Updating tickets

```php
HelpDesk::updateTicket($ticket, [
    'priority' => 'urgent',
    'category_id' => $category->id,
    'due_at' => now()->addDays(3),
]);
```

### Deleting tickets (soft delete)

```php
HelpDesk::deleteTicket($ticket);
```

## Comments

### Adding replies and notes

```php
// Public reply (visible to end user)
$comment = HelpDesk::addComment($ticket, $user, 'Thank you for contacting us.');

// Internal note (not visible to end user)
$note = HelpDesk::addNote($ticket, $operator, 'Escalating to senior engineer.');

// With attachments
$comment = HelpDesk::addComment($ticket, $user, 'See attached screenshot.', [
    'attachments' => [$uploadedFile],
]);
```

## Watchers

```php
HelpDesk::addWatcher($ticket, $anotherUser);
HelpDesk::removeWatcher($ticket, $anotherUser);
```

## Querying Tickets

### Model scopes

```php
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Enums\TicketStatus;
use JeffersonGoncalves\HelpDesk\Enums\TicketPriority;

Ticket::open()->get();                              // Not closed or resolved
Ticket::closed()->get();                             // Closed or resolved
Ticket::byStatus(TicketStatus::InProgress)->get();   // By specific status
Ticket::byPriority(TicketPriority::Urgent)->get();   // By specific priority
Ticket::overdue()->get();                            // Past due_at and still open
Ticket::unassigned()->get();                         // No operator assigned
```

### Via user relationships

```php
// User's tickets
$user->helpDeskTickets;
$user->helpDeskComments;
$user->helpDeskWatching;

// Operator's assigned tickets and departments
$operator->helpDeskAssignedTickets;
$operator->helpDeskDepartments;
$operator->helpDeskHistory;
```

### Ticket instance methods

```php
$ticket->isOpen();      // Not closed or resolved
$ticket->isClosed();    // Status is Closed
$ticket->isResolved();  // Status is Resolved
$ticket->isAssigned();  // Has assigned operator
$ticket->isOverdue();   // Past due_at and still open
```

## Using Services Directly

For more control, inject the service classes:

```php
use JeffersonGoncalves\HelpDesk\Services\TicketService;
use JeffersonGoncalves\HelpDesk\Services\CommentService;
use JeffersonGoncalves\HelpDesk\Services\DepartmentService;
use JeffersonGoncalves\HelpDesk\Services\AttachmentService;

class TicketController
{
    public function __construct(
        private TicketService $tickets,
        private CommentService $comments,
    ) {}

    public function store(Request $request)
    {
        return $this->tickets->create([
            'title' => $request->title,
            'description' => $request->description,
            'department_id' => $request->department_id,
        ], $request->user());
    }
}
```

## Events

Listen to these events in your application:

| Event | Properties |
|-------|-----------|
| `TicketCreated` | `$ticket` |
| `TicketUpdated` | `$ticket`, `$changes` |
| `TicketStatusChanged` | `$ticket`, `$oldStatus`, `$newStatus`, `$performer` |
| `TicketPriorityChanged` | `$ticket`, `$oldPriority`, `$newPriority`, `$performer` |
| `TicketAssigned` | `$ticket`, `$operator`, `$assignedBy` |
| `TicketClosed` | `$ticket`, `$performer` |
| `TicketReopened` | `$ticket`, `$performer` |
| `TicketDeleted` | `$ticket`, `$performer` |
| `CommentAdded` | `$ticket`, `$comment` |
| `AttachmentAdded` | `$ticket`, `$attachment` |
| `AttachmentRemoved` | `$ticket`, `$attachment` |

### Custom event handling

```php
// config/help-desk.php
'register_default_listeners' => false,

// Then in your EventServiceProvider or listener:
use JeffersonGoncalves\HelpDesk\Events\TicketCreated;

Event::listen(TicketCreated::class, function (TicketCreated $event) {
    // Custom logic
    $ticket = $event->ticket;
});
```

## Categories

```php
use JeffersonGoncalves\HelpDesk\Models\Category;

$category = Category::create([
    'department_id' => $department->id,
    'name' => 'Billing',
    'slug' => 'billing',
    'is_active' => true,
]);

// Subcategories
$sub = Category::create([
    'department_id' => $department->id,
    'parent_id' => $category->id,
    'name' => 'Refunds',
    'slug' => 'refunds',
]);
```

## Canned Responses

```php
use JeffersonGoncalves\HelpDesk\Models\CannedResponse;

CannedResponse::create([
    'title' => 'Greeting',
    'body' => 'Thank you for contacting our support team...',
    'department_id' => $department->id,
    'is_active' => true,
]);

$responses = CannedResponse::active()
    ->forDepartment($department->id)
    ->ordered()
    ->get();
```

## Email Integration

### Configure inbound driver

Set the driver in `.env`:

```env
# Available: imap, mailgun, sendgrid, resend, postmark
HELPDESK_INBOUND_DRIVER=mailgun
```

### IMAP polling

```env
HELPDESK_INBOUND_DRIVER=imap
HELPDESK_IMAP_HOST=imap.example.com
HELPDESK_IMAP_PORT=993
HELPDESK_IMAP_USERNAME=support@example.com
HELPDESK_IMAP_PASSWORD=your-password
```

Schedule the polling command:

```php
$schedule->command('help-desk:poll-imap')->everyFiveMinutes();
```

### Webhook drivers (Mailgun, SendGrid, Resend, Postmark)

Webhook routes are registered at `{prefix}/{driver}`:
- `POST /help-desk/webhooks/mailgun`
- `POST /help-desk/webhooks/sendgrid`
- `POST /help-desk/webhooks/resend`
- `POST /help-desk/webhooks/postmark`

### Email channels

```php
use JeffersonGoncalves\HelpDesk\Models\EmailChannel;

EmailChannel::create([
    'department_id' => $department->id,
    'name' => 'Support Inbox',
    'driver' => 'mailgun',
    'email_address' => 'support@example.com',
    'settings' => [], // Driver-specific (encrypted)
    'is_active' => true,
]);
```

## Artisan Commands

```bash
php artisan help-desk:poll-imap              # Poll IMAP mailboxes
php artisan help-desk:clean-emails --days=30 # Clean processed inbound emails
php artisan help-desk:close-stale --days=14  # Auto-close stale resolved tickets
php artisan help-desk:close-stale --dry-run  # Preview without closing
```

## Translations

Publish and customize translations:

```bash
php artisan vendor:publish --tag=help-desk-translations
```

Use translation keys:

```php
__('help-desk::tickets.messages.created')  // "Ticket created successfully."
__('help-desk::statuses.open')             // "Open"
__('help-desk::priorities.urgent')         // "Urgent"
```

Supported locales: `en`, `pt_BR`.
