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

### Scopes and kind helpers

A comment is a `reply`, a `note`, or a `system` entry written by the package. A system
comment has no author, which is why `$comment->author` is nullable.

```php
$ticket->comments()->public()->get();    // everything the requester may read
$ticket->comments()->internal()->get();
$ticket->comments()->replies()->get();
$ticket->comments()->notes()->get();

$comment->isReply();
$comment->isNote();
$comment->isSystem();
$comment->isInternal();
```

## Attachments

`addComment(['attachments' => ...])` covers the common case. Use the service when the file
is not tied to a comment you are creating in the same call.

```php
$service = HelpDesk::attachments();

// Validate first — neither method enforces the limits for you, so you can
// reject a file with your own message
$service->isAllowedExtension($file->getClientOriginalExtension());
$service->isWithinSizeLimit($file->getSize() / 1024); // KB

$attachment = $service->store($ticket, $file, $user);            // from an upload
$attachment = $service->store($ticket, $file, $user, $comment);  // tied to a comment

// From a file already on disk — the inbound email path uses this
$attachment = $service->storeFromPath(
    $ticket, '/tmp/scan.pdf', 'scan.pdf', 'application/pdf', filesize('/tmp/scan.pdf'), $user,
);

$service->delete($attachment, $operator); // removes the row and the stored file
```

Reading one back:

```php
$attachment->getUrl();
$attachment->getTemporaryUrl(5);      // signed URL; needs S3 or similar, not `local`
$attachment->getFileSizeForHumans();  // '1.44 MB'
$attachment->uploader_name;
```

## Ticket History

Every status change, assignment, comment and attachment is recorded by the
`LogTicketHistory` subscriber, as long as the default listeners are registered.

```php
use JeffersonGoncalves\HelpDesk\Enums\HistoryAction;

foreach ($ticket->history()->latest()->get() as $entry) {
    $entry->action;             // HistoryAction enum
    $entry->field;              // 'status', 'priority', 'assigned_to', or null
    $entry->old_value;
    $entry->new_value;
    $entry->description;
    $entry->performer_name;     // never read $entry->performer — see below
}

$ticket->history()->where('action', HistoryAction::StatusChanged)->get();
```

## Watchers

```php
HelpDesk::addWatcher($ticket, $anotherUser);   // adding twice is a no-op
HelpDesk::removeWatcher($ticket, $anotherUser);

foreach ($ticket->watchers as $row) {
    $row->watcher_name;
    $row->resolvedWatcher();
}
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

## Reading the people on a ticket

**Never read the morph relation directly.** `$ticket->user`, `$comment->author`,
`$attachment->uploadedBy`, `$entry->performer` and `$row->watcher` are fatal, not null,
when the stored morph type names a class this application does not have — Eloquent
instantiates the stored class name and PHP raises `Class "..." not found`.

Each model stores an identity snapshot in `metadata` and exposes accessors that prefer the
live model and fall back to the copy:

```php
$ticket->requester_name;        $ticket->requester();
$comment->author_name;          $comment->resolvedAuthor();
$attachment->uploader_name;     $attachment->resolvedUploadedBy();
$entry->performer_name;         $entry->resolvedPerformer();
$row->watcher_name;             $row->resolvedWatcher();
```

Each also has an `_email` counterpart. The `resolved*()` methods return the model, or null
when it is not installed here — and for a system comment or a system action, which have no
person at all.

The snapshot records who acted at the time, so a later rename or delete does not rewrite
history. A user model whose display fields are named differently overrides it:

```php
// App\Models\User
public function toHelpDeskSnapshot(): array
{
    return ['name' => $this->full_name, 'email' => $this->contact_email];
}
```

Notifications follow the same rule. `Ticket::notifyRequester()` goes through the model
when it resolves and sends an on-demand mail notification to the snapshot address
otherwise, so an application can reply to a requester it cannot load.

## Sharing one database across applications

A central support application and several satellites — each exposing only the end-user
side — can point at the same help desk database. No migration declares a foreign key to
`users`, so the tickets can live in a database that knows nothing about any user table.

```env
HELPDESK_DB_CONNECTION=help_desk
HELPDESK_APP_KEY=app-a
HELPDESK_APP_NAME="Application A"
HELPDESK_SCOPE_TO_APP=true
```

`help-desk.connection` routes every model and every package migration. Leaving it null
keeps the application default.

Five things to get right:

1. **The central application owns the schema.** Satellites install the package and set the
   connection, but must not publish or run the help desk migrations — Laravel tracks
   applied migrations in each application's own default connection, so a satellite would
   try to create tables that already exist.
2. **Morph aliases.** Applications with separate `users` tables need a distinct alias
   each, or user `#5` of two applications produce the same requester key:
   ```php
   // AppServiceProvider::boot()
   use Illuminate\Database\Eloquent\Relations\Relation;

   Relation::enforceMorphMap(['app-a-user' => \App\Models\User::class]);
   ```
3. **Read people through the accessors**, per the section above.
4. **Attachments need a shared disk.** `attachment_disk` defaults to `local`, which keeps
   uploads on whichever application received them.
5. **`scope_to_app` off in the central application**, which needs to see every ticket.

```php
Ticket::forApp('app-a')->open()->count();

$ticket->app_key;   // 'app-a'
$ticket->app_name;  // 'Application A', falling back to the key
```

The label is stored on the ticket rather than looked up, because the central application
has no configuration describing the applications it serves.

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
| `AttachmentRemoved` | `$ticket`, `$attachment`, `$removedBy` |
| `InboundEmailReceived` | `$inboundEmail` |
| `InboundEmailProcessed` | `$inboundEmail` |

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
