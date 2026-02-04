# Laravel Help Desk

A comprehensive help desk and ticket management system for Laravel applications with email integration.

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12

## Installation

```bash
composer require jeffersongoncalves/laravel-help-desk
```

The package uses Laravel's auto-discovery, so the service provider and facade are registered automatically.

### Publish Configuration

```bash
php artisan vendor:publish --tag=help-desk-config
```

### Publish Migrations

```bash
php artisan vendor:publish --tag=help-desk-migrations
```

### Run Migrations

```bash
php artisan migrate
```

### Publish Translations (optional)

```bash
php artisan vendor:publish --tag=help-desk-translations
```

## Configuration

The configuration file is located at `config/help-desk.php`. Key options:

```php
return [
    // Models used by the help desk
    'models' => [
        'user'     => \App\Models\User::class,  // Model that creates tickets
        'operator' => \App\Models\User::class,   // Model that manages tickets
    ],

    // Ticket settings
    'ticket' => [
        'reference_prefix'   => 'HD',           // Ticket reference format: HD-00001
        'default_status'     => 'open',
        'default_priority'   => 'medium',
        'attachment_disk'    => 'local',         // Storage disk for attachments
        'auto_close_days'    => null,            // Auto-close resolved tickets (null = disabled)
        'allow_reopen'       => true,
    ],

    // Email integration
    'email' => [
        'enabled' => true,
        'inbound' => [
            'driver' => null, // 'imap', 'mailgun', 'sendgrid', or 'resend'
        ],
    ],

    // Notification settings
    'notifications' => [
        'channels' => ['mail'],
        'queue'    => 'default',
    ],
];
```

## Setup

### 1. Add Traits to Your User Model

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

### 2. Create Departments

```php
use JeffersonGoncalves\HelpDesk\Facades\HelpDesk;

$department = HelpDesk::createDepartment([
    'name' => 'Technical Support',
    'slug' => 'technical-support',
    'email' => 'support@example.com',
    'is_active' => true,
]);
```

### 3. Assign Operators to Departments

```php
HelpDesk::addOperator($department, $user, 'operator'); // 'operator', 'manager', or 'admin'
```

## Usage

### Creating Tickets

```php
use JeffersonGoncalves\HelpDesk\Facades\HelpDesk;

$ticket = HelpDesk::createTicket([
    'title' => 'Cannot access my account',
    'description' => 'I get an error when trying to log in...',
    'department_id' => $department->id,
    'priority' => 'high',
], $user);

// $ticket->reference_number => "HD-00001"
// $ticket->uuid => "550e8400-e29b-41d4-a716-446655440000"
```

### Managing Tickets

```php
// Find tickets
$ticket = HelpDesk::findTicketByReference('HD-00001');
$ticket = HelpDesk::findTicketByUuid('550e8400-...');

// Assign to operator
HelpDesk::assignTicket($ticket, $operator);
HelpDesk::unassignTicket($ticket);

// Change status
use JeffersonGoncalves\HelpDesk\Enums\TicketStatus;

HelpDesk::changeStatus($ticket, TicketStatus::InProgress);
HelpDesk::closeTicket($ticket);
HelpDesk::reopenTicket($ticket);

// Update ticket
HelpDesk::updateTicket($ticket, [
    'priority' => 'urgent',
    'category_id' => $category->id,
]);

// Delete ticket (soft delete)
HelpDesk::deleteTicket($ticket);
```

### Comments

```php
// Add a public reply
$comment = HelpDesk::addComment($ticket, $user, 'Thank you for contacting us.');

// Add an internal note (not visible to end user)
$note = HelpDesk::addNote($ticket, $operator, 'Escalating to senior engineer.');

// Add comment with attachments
$comment = HelpDesk::addComment($ticket, $user, 'See attached screenshot.', [
    'attachments' => [$uploadedFile],
]);
```

### Watchers

```php
HelpDesk::addWatcher($ticket, $anotherUser);
HelpDesk::removeWatcher($ticket, $anotherUser);
```

### Querying Tickets

```php
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Enums\TicketStatus;
use JeffersonGoncalves\HelpDesk\Enums\TicketPriority;

// Open tickets
$open = Ticket::open()->get();

// Closed tickets
$closed = Ticket::closed()->get();

// By status
$inProgress = Ticket::byStatus(TicketStatus::InProgress)->get();

// By priority
$urgent = Ticket::byPriority(TicketPriority::Urgent)->get();

// Overdue tickets
$overdue = Ticket::overdue()->get();

// Unassigned tickets
$unassigned = Ticket::unassigned()->get();

// User's tickets (via trait)
$user->helpDeskTickets;

// Operator's assigned tickets (via trait)
$operator->helpDeskAssignedTickets;
```

### Canned Responses

```php
use JeffersonGoncalves\HelpDesk\Models\CannedResponse;

CannedResponse::create([
    'title' => 'Greeting',
    'body' => 'Thank you for contacting our support team...',
    'department_id' => $department->id,
    'is_active' => true,
]);

// Get canned responses for a department
$responses = CannedResponse::active()
    ->forDepartment($department->id)
    ->ordered()
    ->get();
```

### Categories

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

## Ticket Statuses

| Status | Description |
|--------|-------------|
| `open` | New ticket, awaiting response |
| `pending` | Awaiting user response |
| `in_progress` | Being worked on by an operator |
| `on_hold` | Temporarily on hold |
| `resolved` | Issue has been resolved |
| `closed` | Ticket is closed |

Status transitions are validated automatically. For example, a `closed` ticket can only transition to `open` (reopen).

## Ticket Priorities

| Priority | Numeric Value |
|----------|---------------|
| `low` | 1 |
| `medium` | 2 |
| `high` | 3 |
| `urgent` | 4 |

## Events

The package dispatches events that you can listen to in your application:

| Event | Description |
|-------|-------------|
| `TicketCreated` | A new ticket was created |
| `TicketUpdated` | A ticket was updated |
| `TicketStatusChanged` | Ticket status changed |
| `TicketPriorityChanged` | Ticket priority changed |
| `TicketAssigned` | Ticket was assigned to an operator |
| `TicketClosed` | Ticket was closed |
| `TicketReopened` | Ticket was reopened |
| `TicketDeleted` | Ticket was deleted |
| `CommentAdded` | A comment was added to a ticket |
| `AttachmentAdded` | An attachment was added |
| `AttachmentRemoved` | An attachment was removed |
| `InboundEmailReceived` | An inbound email was received |
| `InboundEmailProcessed` | An inbound email was processed |

### Disabling Default Listeners

If you want to handle events yourself:

```php
// config/help-desk.php
'register_default_listeners' => false,
```

## Email Integration

### Outbound Notifications

Notifications are sent automatically when events occur (configurable via `notifications.notify_on`). Email threading is supported via `Message-ID`, `In-Reply-To`, and `References` headers.

### Inbound Email

The package supports receiving emails via 4 drivers:

#### IMAP

```env
HELPDESK_INBOUND_DRIVER=imap
HELPDESK_IMAP_HOST=imap.example.com
HELPDESK_IMAP_PORT=993
HELPDESK_IMAP_ENCRYPTION=ssl
HELPDESK_IMAP_USERNAME=support@example.com
HELPDESK_IMAP_PASSWORD=your-password
HELPDESK_IMAP_FOLDER=INBOX
```

Requires the `webklex/php-imap` package:

```bash
composer require webklex/php-imap
```

Schedule the polling command in your `app/Console/Kernel.php` or `routes/console.php`:

```php
$schedule->command('help-desk:poll-imap')->everyFiveMinutes();
```

#### Mailgun

```env
HELPDESK_INBOUND_DRIVER=mailgun
HELPDESK_MAILGUN_SIGNING_KEY=your-signing-key
```

Configure your Mailgun route to forward to:
```
POST https://your-app.com/help-desk/webhooks/mailgun
```

#### SendGrid

```env
HELPDESK_INBOUND_DRIVER=sendgrid
HELPDESK_SENDGRID_WEBHOOK_USERNAME=your-username
HELPDESK_SENDGRID_WEBHOOK_PASSWORD=your-password
```

Configure your SendGrid Inbound Parse to forward to:
```
POST https://your-app.com/help-desk/webhooks/sendgrid
```

#### Resend

```env
HELPDESK_INBOUND_DRIVER=resend
HELPDESK_RESEND_API_KEY=re_your-api-key
HELPDESK_RESEND_WEBHOOK_SECRET=whsec_your-webhook-secret
```

Configure your Resend receiving domain webhook to forward to:
```
POST https://your-app.com/help-desk/webhooks/resend
```

Select the `email.received` event type in your Resend webhook configuration.

### Email Channels

You can configure multiple email channels, each mapped to a department:

```php
use JeffersonGoncalves\HelpDesk\Models\EmailChannel;

EmailChannel::create([
    'department_id' => $department->id,
    'name' => 'Support Inbox',
    'driver' => 'mailgun',
    'email_address' => 'support@example.com',
    'settings' => [], // Driver-specific settings (encrypted)
    'is_active' => true,
]);
```

### Email Threading

When an inbound email is received, the package resolves it to an existing ticket using:
1. `In-Reply-To` header
2. `References` header
3. Subject line reference number (e.g., `HD-00001`)

If no match is found, a new ticket is created.

## Artisan Commands

```bash
# Poll IMAP mailboxes for new emails
php artisan help-desk:poll-imap

# Clean old processed inbound emails
php artisan help-desk:clean-emails --days=30

# Auto-close stale tickets
php artisan help-desk:close-stale --days=14 --status=resolved

# Dry run (see what would be closed)
php artisan help-desk:close-stale --days=14 --dry-run
```

## Using the Services Directly

For more control, you can inject the service classes directly:

```php
use JeffersonGoncalves\HelpDesk\Services\TicketService;
use JeffersonGoncalves\HelpDesk\Services\CommentService;
use JeffersonGoncalves\HelpDesk\Services\DepartmentService;
use JeffersonGoncalves\HelpDesk\Services\AttachmentService;

class MyController
{
    public function __construct(
        private TicketService $tickets,
        private CommentService $comments,
    ) {}

    public function store(Request $request)
    {
        $ticket = $this->tickets->create([
            'title' => $request->title,
            'description' => $request->description,
            'department_id' => $request->department_id,
        ], $request->user());

        return $ticket;
    }
}
```

## Translation

The package ships with English and Brazilian Portuguese translations. To customize:

```bash
php artisan vendor:publish --tag=help-desk-translations
```

This publishes translation files to `lang/vendor/help-desk/`. You can modify them or add new locales.

```php
// Using translations in your code
__('help-desk::tickets.messages.created')  // "Ticket created successfully."
__('help-desk::statuses.open')             // "Open"
__('help-desk::priorities.urgent')         // "Urgent"
```

## Testing

```bash
composer test
```

## Static Analysis

```bash
composer analyse
```

## Code Formatting

```bash
composer format
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
