<div class="filament-hidden">

![Laravel Help Desk](https://raw.githubusercontent.com/jeffersongoncalves/laravel-help-desk/master/art/jeffersongoncalves-laravel-help-desk.png)

</div>

# Laravel Help Desk

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jeffersongoncalves/laravel-help-desk.svg?style=flat-square)](https://packagist.org/packages/jeffersongoncalves/laravel-help-desk)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/jeffersongoncalves/laravel-help-desk/tests.yml?branch=master&label=tests&style=flat-square)](https://github.com/jeffersongoncalves/laravel-help-desk/actions?query=workflow%3Atests+branch%3Amaster)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/jeffersongoncalves/laravel-help-desk/fix-php-code-style-issues.yml?branch=master&label=code%20style&style=flat-square)](https://github.com/jeffersongoncalves/laravel-help-desk/actions?query=workflow%3A"Fix+PHP+code+styling"+branch%3Amaster)
[![PHPStan](https://img.shields.io/github/actions/workflow/status/jeffersongoncalves/laravel-help-desk/phpstan.yml?branch=master&label=PHPStan&style=flat-square)](https://github.com/jeffersongoncalves/laravel-help-desk/actions?query=workflow%3APHPStan+branch%3Amaster)
[![Total Downloads](https://img.shields.io/packagist/dt/jeffersongoncalves/laravel-help-desk.svg?style=flat-square)](https://packagist.org/packages/jeffersongoncalves/laravel-help-desk)
[![Buy Me A Coffee](https://img.shields.io/badge/Buy%20Me%20A%20Coffee-support-FFDD00?style=flat-square&logo=buy-me-a-coffee&logoColor=black)](https://buymeacoffee.com/jeffersongoncalves)

A comprehensive help desk and ticket management system for Laravel applications with email integration.

## Requirements

- PHP 8.2+
- Laravel 11, 12, or 13

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
    // Database connection used by the help desk tables and migrations
    // (null = the application's default connection)
    'connection' => env('HELPDESK_DB_CONNECTION'),

    // Identifies this application on the tickets it creates
    'app' => [
        'key'  => env('HELPDESK_APP_KEY'),   // null = single application
        'name' => env('HELPDESK_APP_NAME'),
    ],

    // Read only this application's own tickets
    'scope_to_app' => env('HELPDESK_SCOPE_TO_APP', false),

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
            'driver' => null, // 'imap', 'mailgun', 'sendgrid', 'resend', or 'postmark'
        ],
    ],

    // Notification settings
    'notifications' => [
        'channels' => ['mail'],
        'queue'    => 'default',
    ],
];
```

### Transports

A satellite application reaches the help desk one of two ways, and the choice comes first
because everything else follows from it.

| | `database` | `api` |
|---|---|---|
| How | shares the central database connection | signed HTTP to the central application |
| Needs | credentials for the support database | a shared secret |
| Suits | applications you run, on one network | a satellite that should hold no database credentials |
| Can do | everything | the end-user side only |

```env
HELPDESK_DRIVER=database   # the default
```

The sections below cover the shared connection. [Talking Over the Signed API](#talking-over-the-signed-api)
covers the other.

### Dedicated Database Connection

Set `help-desk.connection` to route every help desk table, model and migration to a
connection other than the application default:

```env
HELPDESK_DB_CONNECTION=help_desk
```

```php
// config/database.php
'connections' => [
    'help_desk' => [
        'driver'   => 'mysql',
        'host'     => env('HELPDESK_DB_HOST'),
        'database' => env('HELPDESK_DB_DATABASE'),
        'username' => env('HELPDESK_DB_USERNAME'),
        'password' => env('HELPDESK_DB_PASSWORD'),
        // ...
    ],
],
```

Leaving it unset keeps the current behaviour, so existing installations need no change.

### Sharing One Help Desk Database Across Applications

Several applications can point at the same help desk database — for example a central
support application running the admin side, and satellite applications exposing only the
end-user side. No migration declares a foreign key to `users`, so the tickets can live in
a database that knows nothing about any application's user table.

Each application sets the same connection credentials:

```env
HELPDESK_DB_CONNECTION=help_desk
```

The central application owns the schema. Satellite applications install the package and
point at the connection, but must **not** publish or run the help desk migrations —
Laravel tracks applied migrations in each application's own default connection, so a
satellite would try to create tables that already exist.

Three things need attention in this topology:

- **Attachments.** `help-desk.ticket.attachment_disk` defaults to `local`, which keeps
  uploads on the disk of whichever application received them. Point every application at a
  **shared disk** (S3 or equivalent) so the admin side can serve files uploaded elsewhere.
- **Requester identity.** Tickets reference their requester through a polymorphic
  `user_type` / `user_id` pair. When applications keep separate `users` tables, register a
  distinct morph alias per application so the keys cannot collide:

  ```php
  use Illuminate\Database\Eloquent\Relations\Relation;

  // AppServiceProvider::boot() of each application
  Relation::enforceMorphMap([
      'app-a-user' => \App\Models\User::class,
  ]);
  ```

  Without this, user `#5` of two different applications produce the same requester key.

### Telling Applications Apart

Give each application a key. Tickets it creates carry it, so the central application can
group, filter and route them:

```env
HELPDESK_APP_KEY=app-a
HELPDESK_APP_NAME="Application A"
```

```php
Ticket::forApp('app-a')->open()->count();

$ticket->app_key;   // 'app-a'
$ticket->app_name;  // 'Application A', falling back to the key
```

The label is stored on the ticket rather than looked up, because the central application
has no configuration describing the applications it serves.

A satellite application can also refuse to read anything but its own tickets:

```env
HELPDESK_SCOPE_TO_APP=true
```

This applies to every query on `Ticket`, not only the ones that filter by requester, and
it complements the per-application morph alias above rather than replacing it. Leave it off
in the central application, which needs to see them all.

Both are optional: with no key configured a ticket stores none and nothing is scoped, which
is the single-application behaviour.

### Naming People From Another Application

An application can only load the requester of a ticket it created itself. Everywhere else
the stored morph type points at a model that is not installed, or that lives in a database
this application cannot reach.

So the requester's name and email are **copied onto the ticket** when it is created, and
the same is done for every comment author, attachment uploader, history entry and watcher.
Read them through the accessors, which use the live model when it resolves and the copy
when it does not:

```php
$ticket->requester_name;   // 'Ada Lovelace'
$ticket->requester_email;  // 'ada@example.com'

$comment->author_name;
$comment->author_email;

$attachment->uploader_name;
$attachment->uploader_email;

$historyEntry->performer_name;
$watcherRow->watcher_name;
```

```php
$ticket->requester();                // the model, or null when not installed here
$comment->resolvedAuthor();          // same, and null for system comments
$attachment->resolvedUploadedBy();   // same
$historyEntry->resolvedPerformer();  // same, and null for a system action
$watcherRow->resolvedWatcher();      // same
```

Reading the raw relation — `$ticket->user`, `$comment->author`, `$attachment->uploadedBy`,
`$entry->performer`, `$row->watcher` — still throws for a model this application does not
have, because Eloquent instantiates the stored class name. Use the methods above instead.

Notifications follow the same rule. `Ticket::notifyRequester()` goes through the model when
it resolves and falls back to an on-demand mail notification to the copied address
otherwise, so the central application can reply to a requester it cannot load.

The copy is a snapshot, not a join: it records who opened the ticket at the time, and a
later rename or deletion does not rewrite history. If your user model exposes its display
fields under other names, override them:

```php
// App\Models\User
public function toHelpDeskSnapshot(): array
{
    return ['name' => $this->full_name, 'email' => $this->contact_email];
}
```

### Talking Over the Signed API

When a satellite should hold no credentials for the support database — it runs on another
host, or on someone else's infrastructure — it reaches the central application over HTTP
instead, authenticated with HMAC.

Calling code does not change. The facade is the same, and what comes back is still a
`Ticket`:

```php
$ticket = HelpDesk::createTicket([
    'department_id' => $department->id,
    'title' => 'Printer offline',
    'description' => 'It stopped printing.',
], $user);

$ticket->reference_number;  // 'HD-00042'
$ticket->isOpen();          // true
$ticket->requester_name;    // 'Ada Lovelace'
```

#### On the satellite

```env
HELPDESK_DRIVER=api
HELPDESK_API_URL=https://support.example.com
HELPDESK_APP_KEY=app-a
HELPDESK_API_SECRET=a-long-random-string
```

#### On the central application

```php
// config/help-desk.php
'api' => [
    'clients' => [
        'app-a' => [
            'secrets' => [
                env('HELPDESK_SECRET_APP_A'),
                env('HELPDESK_SECRET_APP_A_PREVIOUS'),
            ],
            'actor_types' => ['app-a-user'],
        ],
    ],
],
```

Two secrets, current first, so one can be rotated without a flag day: deploy the new
secret, roll the satellites, then drop the old entry. `actor_types` lists the morph aliases
that application's users are stored under; claiming any other is rejected.

**No clients configured means no API routes are registered at all**, so a single
application installation exposes nothing.

#### What the signature proves, and what it does not

It proves **which application** is calling. The acting user is **asserted by** that
application in the payload.

So a leaked secret can impersonate any user *of that application*, and none of another —
the app key comes from the signed header, never the body, and every read is scoped by it.
HMAC gives authenticity and integrity, not confidentiality: **HTTPS is still required**.

#### Reading a list, on either transport

The four methods a user-facing panel needs are on the contracts, so the same call works
under both drivers and nothing has to branch on which one is configured:

```php
// The tickets this user opened, newest first. Paginated, because the API
// endpoint behind it always was.
$tickets = HelpDesk::tickets()->forActor($user);
$tickets = HelpDesk::tickets()->forActor($user, perPage: 15, page: 2);

// Filtered, searched and sorted by the database — on both drivers.
$tickets = HelpDesk::tickets()->forActor($user,
    status: [TicketStatus::Open, 'in_progress'],   // strings or enums
    priority: TicketPriority::Urgent,
    search: 'scanner',                             // title and reference number
    sort: 'priority', direction: 'desc',           // one of Ticket::SORTABLE
);

// The options a create form offers: active only, in sort order.
HelpDesk::departments()->all();
HelpDesk::departments()->categoriesFor($department->id);

// The bytes of an attachment, for handing a file back to its uploader.
HelpDesk::attachments()->contents($attachment, $ticket->uuid);
```

`forActor()` takes the user rather than falling back to whoever is authenticated. Which
tickets someone may see is not a decision to make by omission.

The filters are on the contract rather than left to the caller because the API driver
cannot do them in memory: narrowing the page it happens to hold and presenting that as
"your open tickets" looks filtered and is wrong. They are applied after the actor scope,
so they only ever narrow what is asked for.

A few details worth knowing:

- `status` and `priority` take a value, an enum case, or a list of either. An unknown one
  throws rather than matching nothing — an empty list from a typo looks exactly like an
  empty list from having no tickets.
- `search` covers `title` and `reference_number`, case-insensitively. Not the description:
  it is rich text, and matching the markup produces hits the user cannot see in the row.
  `%` and `_` in the term are the characters the user typed, not wildcards.
- `sort` is an allow-list — `Ticket::SORTABLE` — checked on the client *and* again on the
  central application, because a caller-supplied column reaches the query builder. Sorting
  by `priority` or `status` orders by the enum's own sequence (severity, lifecycle), not by
  the stored string, which would put `high` above `low` and call it sorted.

Over the API these are query parameters on `GET tickets`: `status[]`, `priority[]`, `q`,
`sort` and `direction`. Anything the allow-lists do not name is a 422.

The ticket uuid on `contents()` is not redundant: it is what the API path needs, and both
transports check it against the attachment, so a mismatched pair fails the same way
instead of serving a file from another ticket.

#### Closing and reopening

The two status changes that belong to the person who opened the ticket work over the API:

```php
HelpDesk::closeTicket($ticket, $user);
HelpDesk::reopenTicket($ticket, $user);
```

Every other status is an operator decision and has no endpoint, so the driver refuses it
locally rather than sending a request the central application would reject:

```php
HelpDesk::changeStatus($ticket, TicketStatus::Resolved, $user);
// HelpDeskApiException: Changing a ticket to resolved is an operator action and
// the API driver cannot perform it.
```

The allow-list is enforced on the central application as well, not only in the client — a
satellite is not a trust boundary. The transition table still applies to both moves,
including `help-desk.ticket.allow_reopen`, so a refused move raises
`InvalidStatusTransitionException` on either driver.

#### What the API driver cannot do

The rest of the operator surface throws immediately, naming what to use instead, rather
than making a request that would be refused:

```php
HelpDesk::assignTicket($ticket, $operator);
// HelpDeskApiException: assignTicket() is an operator action and the API driver
// cannot perform it. It belongs to the central application, on the database driver.
```

That covers updating, the four operator statuses, assignment, deletion, internal notes,
watchers, and managing departments.

#### Attachments

A file travels base64 encoded inside the JSON body, so the signature covers it like any
other payload and no multipart handling is involved.

```php
$attachment = HelpDesk::attachments()->store($ticket, $request->file('file'), $user);

$attachment->file_name;      // 'invoice.pdf'
$attachment->uploader_name;  // 'Ada Lovelace'
```

The cost of sending it inline is a cap, because the file grows by a third in transit and is
held in memory on both ends:

```env
HELPDESK_API_MAX_INLINE_ATTACHMENT=2048   # KB, on both ends
```

Deliberately smaller than `help-desk.ticket.max_file_size` — it is the ceiling of the
inline approach, not a policy about files. Something larger wants a signed upload URL,
which this does not implement.

The satellite has no access to the disk the file sits on, so there is no URL to hand out:

```php
$attachment->getUrl();
// HelpDeskApiException: getUrl() is not available on the API driver… Use
// HelpDesk::attachments()->contents($attachment) to fetch the bytes instead.

$bytes = HelpDesk::attachments()->contents($attachment, $ticket->uuid);
```

Extension and size limits are enforced on **both** ends. The satellite checks first to
avoid spending a round trip, and the central application checks again because a satellite
is not a trust boundary.

Relations are the other limit. A model that came back over the wire has no database to
join against, so reading a relation the response did not carry throws rather than
producing a missing-table SQL error:

```php
$ticket->comments;
// HelpDeskApiException: Relation [comments] on ApiTicket needs the database driver.
// The show endpoint returns them: use HelpDesk::tickets()->findByUuid($uuid).
```

A relation the response *did* carry — the comments and attachments on a ticket fetched by
uuid — is returned as normal. The show endpoint sends attachments as one flat list, so
`$ticket->attachments` holds all of them and each comment carries its own subset.

Hydrated tickets and attachments are keyed by `uuid`, not `id`: the resources publish the
uuid and never the central application's primary key, so `$ticket->getKey()` returns the
uuid. It matters for anything that keys a collection by the model's key.

#### The signature scheme

Enough to write a client in another language.

```
canonical = METHOD \n REQUEST_URI \n TIMESTAMP \n NONCE \n sha256(RAW_BODY)
signature = "sha256=" + hex(hmac_sha256(canonical, secret))
```

`REQUEST_URI` is the path plus query string, exactly as the server sees it. The method and
URI are in the string because signing the body alone would let a captured request be
replayed against a different endpoint.

| Header | Content |
|---|---|
| `X-HelpDesk-App` | the calling application's app key |
| `X-HelpDesk-Timestamp` | Unix seconds |
| `X-HelpDesk-Nonce` | 128 bits of randomness, 32 hex characters |
| `X-HelpDesk-Signature` | `sha256=<hex>` |

The server rejects a timestamp more than `help-desk.api.tolerance` seconds away in either
direction, and rejects a nonce it has already seen. Both are required: a window alone
leaves everything inside it replayable, and a nonce alone lets a capture be replayed
forever.

Every rejection is the same `401` with the same body, whatever the reason.

#### Endpoints

| Method | Path |
|---|---|
| `POST` | `/help-desk/api/tickets` |
| `GET` | `/help-desk/api/tickets` |
| `GET` | `/help-desk/api/tickets/{uuid}` |
| `POST` | `/help-desk/api/tickets/{uuid}/status` |
| `POST` | `/help-desk/api/tickets/{uuid}/comments` |
| `GET` | `/help-desk/api/departments` |
| `GET` | `/help-desk/api/departments/{id}/categories` |

Every request acting on behalf of a person carries an actor:

```json
{
  "actor": { "type": "app-a-user", "id": 5, "name": "Ada Lovelace", "email": "ada@example.com" },
  "title": "Printer offline",
  "description": "It stopped printing."
}
```

The name and email become the identity snapshot, which is how the central application names
a requester whose model it does not have.

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
HelpDesk::removeOperator($department, $user);

HelpDesk::updateDepartment($department, ['name' => 'Support', 'is_active' => false]);
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

State is readable straight off the model:

```php
$ticket->isOpen();      // anything that is not closed or resolved
$ticket->isClosed();
$ticket->isResolved();
$ticket->isAssigned();
$ticket->isOverdue();   // past due_at and still open
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

Comments come in three kinds — a public `reply`, an internal `note`, and a `system`
entry written by the package itself. Scope by kind, or by whether the end user may see
them:

```php
$ticket->comments()->public()->get();    // everything the requester may read
$ticket->comments()->internal()->get();  // internal notes only
$ticket->comments()->replies()->get();   // type = reply
$ticket->comments()->notes()->get();     // type = note

$comment->isReply();
$comment->isNote();
$comment->isSystem();
$comment->isInternal();
```

A system comment has no author, which is why `$comment->author` is nullable.

### Attachments

Attachments belong to a ticket, and optionally to one comment on it. Passing
`attachments` to `addComment()` covers the common case; reach for the service when you
need the attachment on its own.

```php
use JeffersonGoncalves\HelpDesk\Facades\HelpDesk;

// From an uploaded file
$attachment = HelpDesk::attachments()->store($ticket, $request->file('file'), $user);

// Attached to a specific comment
$attachment = HelpDesk::attachments()->store($ticket, $file, $user, $comment);

// From a file already on disk — the inbound email path uses this
$attachment = HelpDesk::attachments()->storeFromPath(
    $ticket,
    '/tmp/scan.pdf',
    'scan.pdf',
    'application/pdf',
    filesize('/tmp/scan.pdf'),
    $user,
);

// Deletes the row and the stored file, and fires AttachmentRemoved
HelpDesk::attachments()->delete($attachment, $operator);
```

Validate before storing. Neither method enforces the limits for you — they exist so you
can reject a file with your own message:

```php
$service = HelpDesk::attachments();

$service->isAllowedExtension($file->getClientOriginalExtension()); // help-desk.ticket.allowed_extensions
$service->isWithinSizeLimit($file->getSize() / 1024);              // help-desk.ticket.max_file_size, in KB
```

Reading one back:

```php
$attachment->getUrl();                  // public URL on the attachment's disk
$attachment->getTemporaryUrl(5);        // signed URL, valid for 5 minutes, for private disks
$attachment->getFileSizeForHumans();    // '1.44 MB'

$attachment->uploader_name;             // see "Naming People From Another Application"
```

`getTemporaryUrl()` needs a disk that supports signed URLs, such as S3. The `local` disk
does not.

### Watchers

```php
HelpDesk::addWatcher($ticket, $anotherUser);
HelpDesk::removeWatcher($ticket, $anotherUser);

foreach ($ticket->watchers as $row) {
    $row->watcher_name;        // snapshot, so it survives a model this app lacks
    $row->watcher_email;
    $row->resolvedWatcher();   // the model, or null when not installed here
}
```

Adding the same watcher twice is a no-op, so you can call it without checking first.

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

The traits add more than those two. `HasTickets` gives a user:

```php
$user->helpDeskTickets;    // tickets they opened
$user->helpDeskComments;   // comments they wrote, across every ticket
$user->helpDeskWatching;   // TicketWatcher rows for tickets they follow
```

and `IsOperator` adds, on top of those:

```php
$operator->helpDeskAssignedTickets;  // tickets assigned to them
$operator->helpDeskDepartments;      // departments they operate, with a `role` pivot
$operator->helpDeskHistory;          // every action they performed
```

### Ticket History

Every status change, assignment, comment and attachment is recorded, by the
`LogTicketHistory` subscriber, as long as the default listeners are registered.

```php
use JeffersonGoncalves\HelpDesk\Enums\HistoryAction;

foreach ($ticket->history()->latest()->get() as $entry) {
    $entry->action;       // HistoryAction enum
    $entry->field;        // 'status', 'priority', 'assigned_to', or null
    $entry->old_value;
    $entry->new_value;
    $entry->description;
    $entry->performer;    // the model that acted, null for a system action
}

$ticket->history()->where('action', HistoryAction::StatusChanged)->get();
```

Like tickets, comments and attachments, a history row carries an identity snapshot, so
reading it from an application that does not have the performer's model installed gives a
name rather than a crash:

```php
$entry->performer_name;
$entry->performer_email;
$entry->resolvedPerformer();  // the model, or null for a system action
```

Reading `$entry->performer` directly still throws for a model this application does not
have. Use `resolvedPerformer()`.

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

## Exceptions

Lookups and status changes throw rather than returning null, so a controller can let them
bubble to a handler instead of branching on every call.

| Exception | Thrown by | When |
|---|---|---|
| `TicketNotFoundException` | `findTicketByUuid()`, `findTicketByReference()` | No ticket matches, or the UUID is malformed |
| `InvalidStatusTransitionException` | `changeStatus()`, `closeTicket()`, `reopenTicket()` | The current status cannot transition to the requested one — see the table below |
| `UnauthorizedOperatorException` | Yours to throw | Provided for authorization checks; the package does not throw it for you |
| `EmailProcessingException` | The inbound email drivers | An IMAP connection fails, `webklex/php-imap` is missing, a payload will not parse, or no active department exists to route a message to |

All four extend `RuntimeException`.

```php
use JeffersonGoncalves\HelpDesk\Enums\TicketStatus;
use JeffersonGoncalves\HelpDesk\Exceptions\InvalidStatusTransitionException;
use JeffersonGoncalves\HelpDesk\Exceptions\TicketNotFoundException;
use JeffersonGoncalves\HelpDesk\Facades\HelpDesk;

try {
    $ticket = HelpDesk::findTicketByReference($reference);

    HelpDesk::changeStatus($ticket, TicketStatus::Closed, $operator);
} catch (TicketNotFoundException) {
    abort(404);
} catch (InvalidStatusTransitionException $e) {
    return back()->withErrors($e->getMessage());
}
```

Check first when you would rather not catch:

```php
$ticket->status->canTransitionTo(TicketStatus::Closed);
```

Inbound email failures are not thrown at you — `ProcessInboundEmail` catches them and marks
the row failed, so the message is visible on the `InboundEmail` record rather than in a log.

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

The package supports receiving emails via 5 drivers:

> **Security: webhook secrets are mandatory.** The HTTP webhook drivers (Mailgun,
> SendGrid, Resend, and Postmark) verify every request against a configured
> secret/credential and **fail closed**: if the corresponding secret is not set,
> the request is rejected with `403 Forbidden` and a warning is logged. You
> **must** configure the secrets below for each driver you enable, otherwise the
> endpoint will reject all traffic. The webhook routes are also rate limited by
> default (`throttle:60,1`, configurable via `help-desk.webhooks.middleware`).
>
> | Driver | Required configuration |
> |--------|------------------------|
> | Mailgun | `HELPDESK_MAILGUN_SIGNING_KEY` |
> | SendGrid | `HELPDESK_SENDGRID_WEBHOOK_USERNAME` + `HELPDESK_SENDGRID_WEBHOOK_PASSWORD` |
> | Resend | `HELPDESK_RESEND_WEBHOOK_SECRET` (and `HELPDESK_RESEND_API_KEY` to fetch bodies) |
> | Postmark | `HELPDESK_POSTMARK_WEBHOOK_USERNAME` + `HELPDESK_POSTMARK_WEBHOOK_PASSWORD` |

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

#### Postmark

```env
HELPDESK_INBOUND_DRIVER=postmark
HELPDESK_POSTMARK_WEBHOOK_USERNAME=your-username
HELPDESK_POSTMARK_WEBHOOK_PASSWORD=your-password
```

In your Postmark server, go to the Inbound Message Stream settings and set the webhook URL to:
```
POST https://your-username:your-password@your-app.com/help-desk/webhooks/postmark
```

Postmark sends the full email content (body, headers, attachments) directly in the webhook payload. The package also uses Postmark's `StrippedTextReply` field for cleaner reply parsing.

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

## Changelog

Please see [CHANGELOG](https://github.com/jeffersongoncalves/laravel-help-desk/blob/master/CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](https://github.com/jeffersongoncalves/laravel-help-desk/blob/master/.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Jefferson Gonçalves](https://github.com/jeffersongoncalves)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
