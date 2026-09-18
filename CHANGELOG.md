# Changelog

All notable changes to `laravel-help-desk` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Entries are appended automatically on release. For versions up to and including
[v1.4.0](https://github.com/jeffersongoncalves/laravel-help-desk/releases/tag/v1.4.0),
released before this file existed, see the
[releases page](https://github.com/jeffersongoncalves/laravel-help-desk/releases).

## v1.10.1 - 2026-09-18

Two bug fixes reported from production.

### Fixes

- **`EmailChannel.settings` migration column changed from `json()` to `text()`.** The column casts `encrypted:array`, which writes ciphertext, not JSON — MySQL's native `JSON` column type rejected every insert with `SQLSTATE[22032]`. Installs that already ran the old migration get a new one (`change_settings_to_text_in_help_desk_email_channels_table`) to alter the existing column; run `php artisan migrate` after updating. (#70)
- **Threading `Message-ID` header now built with `addIdHeader()` instead of `addTextHeader()`.** Symfony Mime requires `message-id` to be an `IdentificationHeader` — the previous code threw `Symfony\Component\Mime\Exception\LogicException` on every threading-enabled notification send (ticket created, assigned, closed, status changed, comment added). (#71)

No breaking changes. Update and run `php artisan migrate`.

## v1.10.0 - 2026-09-17

Five roadmap gaps closed in one pass: canned response variables, CSAT, SLA policies with breach detection, configurable automation, and a knowledge base foundation. Plus two bug fixes underneath all of them.

Everything here is additive. Nothing changes `TicketService`, `CommentService`, `AttachmentService`, `DepartmentService` or the existing enums' public signatures. Four new migrations, one new column set on `tickets`, no breaking changes.

### Two fixes worth knowing about first

- **`TicketService::update()` now validates a status transition even when `status` arrives inside the `$data` array**, not only through `changeStatus()`. Before this, `$tickets->update($ticket, ['status' => 'closed', 'title' => '...'])` silently bypassed `TicketStatus::canTransitionTo()` — a ticket could jump straight from `open` to `resolved` if the caller happened to pass both fields in one call.
- **`TicketUpdated` now carries the performer on `unassign()` too.** `assign()` always has; `unassign()` accepted a `$performer` parameter and quietly dropped it before this release, so an unassignment's history/event trail showed no one responsible for it.

### Canned response variables

`CannedResponseService::render()` substitutes `{ticket_code}`, `{user_name}`, `{agent_name}` and `{department}` in a response body, plus a registry for your own:

```php
CannedResponseService::resolveVariable('order_number', function (Ticket $ticket, ?Model $agent) {
    return $ticket->metadata['order_number'] ?? '';
});


```
A custom resolver can never override a core placeholder, and a missing value renders as an empty string rather than `null` or an exception — a template with a variable nobody filled in still renders the rest of the message.

### Customer satisfaction (CSAT)

`help_desk_ticket_feedback` (one row per ticket, polymorphic `submitted_by`) and `FeedbackService::submit()`, gated by `Ticket::canReceiveFeedback()`:

```php
if ($ticket->canReceiveFeedback()) {
    $feedback->submit($ticket, rating: 5, comment: '...', submittedBy: $user);
}


```
Feedback is accepted on a `resolved` or `closed` ticket, inside a configurable window (`help-desk.feedback.window_days`, default 14). A rating outside 1–5 is rejected before anything is written.

`help-desk.feedback.auto_reopen` (off by default) reopens a ticket automatically when the rating is at or below a configured threshold, through the normal `TicketService::reopen()` path — so it respects `allow_reopen` and every other transition rule, rather than writing the status column directly.

### SLA policies and breach detection

`help_desk_sla_policies` (department + priority, first-response and resolution minutes, a `business_hours` JSON column) and new columns on `tickets` for the computed due dates. `SlaService::applyPolicy()` resolves the most specific matching policy — department+priority beats department-only beats priority-only beats a generic policy — on `TicketCreated`.

```php
'business_hours' => ['is_24_7' => true],
// or a weekly window in UTC:
'business_hours' => ['mon' => ['09:00', '18:00'], 'tue' => ['09:00', '18:00'], ...],


```
The resolution clock pauses while a ticket sits in `Pending` or `OnHold` — `sla_paused_at` and `total_sla_paused_minutes` push `sla_resolution_due_at` forward by however long it was actually waiting, rather than counting that time against the operator.

`help-desk:check-sla-breaches --dry-run` flags first-response and resolution breaches once each (a breach timestamp column makes it idempotent across runs) and dispatches `TicketSlaFirstResponseBreached` / `TicketSlaResolutionBreached`.

### Configurable automation

`help_desk_automation_rules` (JSON `conditions` and `actions`) generalizes the pattern `CloseStaleTicketsCommand` already used for one hardcoded rule:

```php
AutomationRule::create([
    'name' => 'Close stale pending tickets',
    'conditions' => ['field' => 'last_replied_at', 'operator' => 'older_than_hours', 'value' => 24, 'status' => ['pending']],
    'actions' => [
        ['type' => 'change_status', 'value' => 'closed'],
        ['type' => 'notify', 'notifiable' => 'assigned_to'],
    ],
]);


```
`AutomationService::evaluate()` builds every query from an explicit field/operator allow-list — a rule's JSON never reaches raw SQL — and applies actions exclusively through `TicketService`, so `change_status` is validated by the same transition table as everywhere else. `notify` sends `TicketAutomationTriggeredNotification` to `assigned_to`, `requester`, or every `department_operators`; no resolvable target is a no-op, not an exception.

A `help_desk_ticket_automations_applied` guard table stops a rule from reprocessing the same ticket once it has acted — the anti-loop protection a `notify`-only rule (nothing about the ticket changes to stop it matching again) specifically needs. `help-desk:run-automations --dry-run` runs it from a schedule.

### Knowledge base foundation

`help_desk_kb_articles` (department/category optional, `app_key`-isolated the same way tickets are) and `KnowledgeBaseService`:

```php
$articles = $knowledgeBase->search('reset password', departmentId: 3);
$knowledgeBase->recordView($articles->first());


```
`search()` matches title and body, published-only, with the same escaped-LIKE handling as `Ticket::scopeSearch()` — `%`, `_` and `!` in what a user typed stay literal characters, not SQL wildcards.

Deflection tracking needs no new table: `TicketService::create()` already merges any `metadata` key you pass, so recording which articles were shown before someone opened a ticket anyway is `'metadata' => ['suggested_articles' => [...]]` on the existing `create()` call. Documented in the README.

### A linear pipeline for stepper UIs

`TicketStatus::pipelineSteps()` returns `[Open, InProgress, Resolved, Closed]` — the happy path a visual stepper renders — separately from `allowedTransitions()`'s full branching graph. `pipelineStep()` maps `Pending`/`OnHold` onto `InProgress`, since the ticket is still being worked, just currently waiting on someone else.

### Upgrading

```bash
composer update jeffersongoncalves/laravel-help-desk
php artisan migrate


```
Five new tables (`help_desk_ticket_feedback`, `help_desk_sla_policies`, `help_desk_automation_rules`, `help_desk_ticket_automations_applied`, `help_desk_kb_articles`), new SLA columns on `help_desk_tickets`. Every new feature is opt-in: no SLA policy means no due dates computed, no automation rule means nothing runs, `help-desk.feedback.auto_reopen` and `help-desk.register_default_listeners` default the same way they always have.

**Full Changelog**: https://github.com/jeffersongoncalves/laravel-help-desk/compare/v1.9.0...v1.10.0

## v1.9.0 - 2026-09-16

A user-facing list a satellite can actually narrow, and two bugs that made the API driver's hydrated models quietly wrong.

Nothing here changes `driver=database` behaviour you were already relying on. No migration.

### The list can be filtered, searched and sorted

`GET tickets` took `reference_number`, `per_page` and `page`. Enough to list a user's tickets and resolve one by reference; not enough to answer "my open tickets" or "newest first by priority". On the database driver those are a `where` and an `orderBy`. On a satellite there was nowhere to put them, so a panel had to narrow the page it happened to hold and present that as a filtered list — which looks filtered and is wrong, with nothing on screen saying so.

`forActor()` now takes them, on the contract, so the same call means the same thing on either transport:

```php
$tickets = HelpDesk::tickets()->forActor($user,
    status: [TicketStatus::Open, 'in_progress'],   // strings or enums, one or many
    priority: TicketPriority::Urgent,
    search: 'scanner',                             // title and reference number
    sort: 'priority', direction: 'desc',           // one of Ticket::SORTABLE
);



```
Over the API they are query parameters on `GET tickets`: `status[]`, `priority[]`, `q`, `sort` and `direction`. Every one of them is applied *after* the actor scope, so a filter can only narrow what is asked for — never widen what a satellite may see.

Four decisions in there are worth stating, because each is a place the obvious implementation is silently wrong:

- **`priority` and `status` sort by the enum's own sequence**, not by the string in the column. Alphabetically `high` sorts above `low` and the list still looks sorted.
- **`search` covers `title` and `reference_number`, case-insensitively.** Not the description: it arrives as rich text, and a match in the markup is a hit the user cannot see anywhere in the row. `%` and `_` in the term are the characters the user typed — unescaped, someone searching `90%` would be handed every ticket they own.
- **`sort` is an allow-list**, checked by the client *and* again by the central application, because a caller-supplied column reaches the query builder. A satellite is not a trust boundary.
- **An unknown status, priority, sort column or direction throws** rather than being ignored. A list that came back empty because of a typo looks exactly like one that came back empty because there is nothing to show.

The work lives in four scopes on `Ticket` — `statusIn`, `priorityIn`, `search`, `sorted` — shared by both drivers, so the allow-list and the search shape are defined once rather than twice and drifting.

### A hydrated ticket had no key

`TicketResource` publishes `uuid` and deliberately never `id` — the central application's primary keys are no one else's business. But `ApiTicket` still inherited `$primaryKey = 'id'`, so `getKey()` answered `null` on every ticket that came back over the wire.

Anything keying a collection by the model's key therefore collapsed the whole page into one entry. Filament's table does exactly that: a user with 25 tickets was shown 1. No exception, no log line — the same class of failure as v1.8.0's truncated list.

`ApiTicket` and `ApiTicketAttachment` key on `uuid` now, which is what the endpoints key on and the only identifier their resources publish. `ApiTicketComment` is unchanged, because `TicketCommentResource` does publish `id`.

### The show response's attachments were never models

`hydrateTicket()` lifted `comments` out of the payload but not `attachments`, so `forceFill()` wrote an *attribute* named `attachments` holding an array of arrays. Reading `$ticket->attachments` found that attribute before `GuardsRelations` was ever consulted, and handed back `array` where the contract says `Collection<TicketAttachment>`. A blade doing `$attachment->file_name` failed with "attempt to read property on array" — precisely the unhelpful failure `GuardsRelations` exists to remove, reintroduced through the front door.

Attachments are hydrated now, and because the response sends them as one flat list carrying `comment_id`, each comment gets its own subset too:

```php
$ticket = HelpDesk::tickets()->findByUuid($uuid);

$ticket->attachments;                   // Collection<ApiTicketAttachment>
$ticket->comments->first()->attachments; // its own, from the same response



```
The rule that made the trait worth having still holds: a response that did not carry attachments leaves the relation unset, so reading it throws something legible rather than returning a trustworthy-looking empty collection.

### Upgrading

```bash
composer update jeffersongoncalves/laravel-help-desk



```
No migration, no configuration change. Applications implementing `TicketRepository` themselves need the five new optional parameters on `forActor()`; everything calling the facade is unaffected.

**Full Changelog**: https://github.com/jeffersongoncalves/laravel-help-desk/compare/v1.8.0...v1.9.0

## v1.8.0 - 2026-09-16

Two changes to what a satellite application can do over the signed API, and one bug fix in how it reads a list.

Nothing here changes `driver=database`. No migration.

### Closing and reopening are the requester's

The two transports disagreed about what an end user may do, and nobody decided that. On the database driver a user has closed and reopened their own ticket since the panels shipped. Over the API the same call threw.

```php
HelpDesk::closeTicket($ticket, $user);
HelpDesk::reopenTicket($ticket, $user);




```
`POST /help-desk/api/tickets/{uuid}/status` takes an allow-list of exactly two values. `resolved`, `in_progress`, `pending` and `on_hold` carry operator and SLA meaning and stay unreachable from a satellite — and the allow-list is enforced by the central application, not only by the client, because a satellite is not a trust boundary.

The move still goes through the transition table, so `help-desk.ticket.allow_reopen` is honoured and a refused change raises `InvalidStatusTransitionException` on either driver. The history entry names who closed it from the identity snapshot, exactly as it does for a comment.

Everything else still throws:

```php
HelpDesk::changeStatus($ticket, TicketStatus::Resolved, $user);
// HelpDeskApiException: Changing a ticket to resolved is an operator action and
// the API driver cannot perform it.




```
The line is not "status changes are operator-only". It is "these two are yours, the rest are ours".

### One call, either transport

Four methods a user-facing panel needs lived only on the API implementations, so a caller typed against the contract could not reach them without branching on the driver and casting. They are on the contracts now, implemented on both sides:

```php
$tickets = HelpDesk::tickets()->forActor($user);
$tickets = HelpDesk::tickets()->forActor($user, perPage: 15, page: 2);

HelpDesk::departments()->all();
HelpDesk::departments()->categoriesFor($department->id);

HelpDesk::attachments()->contents($attachment, $ticket->uuid);




```
`forActor()` takes the user rather than falling back to whoever is authenticated. Which tickets someone may see is not a decision to make by omission.

The two implementations are not equally safe by construction, which is worth knowing. Over the API the server scopes by the signed app key *and* the asserted actor, and a satellite cannot widen that. On the database driver the `where` clauses are the only guard, so they are tested against another user's ticket and against another morph type holding the same primary key.

### The list was truncated

`forActor()` returned only `data` from a paginated endpoint. A satellite with 63 tickets was shown 25, with no total, no page count and no error. It now keeps the totals the response was already carrying.

Undocumented and unconsumed in v1.7.0, so nothing in the wild hit it — but it was wrong.

`categoriesFor()` also returned raw arrays over the API where the database side returns `Category` models. Both return models now.

### Upgrading

```bash
composer update jeffersongoncalves/laravel-help-desk




```
No migration, no configuration change. Applications implementing the package's repository contracts themselves need the four new methods; everything calling the facade is unaffected.

**Full Changelog**: https://github.com/jeffersongoncalves/laravel-help-desk/compare/v1.7.0...v1.8.0

## v1.7.0 - 2026-09-16

A second way for a satellite application to reach a central help desk: a signed HTTP API, for when it should hold no credentials for the support database at all.

Everything here is additive. A single application installation, and one sharing a database connection, both behave exactly as they did in v1.6.1 — the new driver is opt-in, and with no API clients configured no endpoints are registered.

### Two transports

| | `database` | `api` |
|---|---|---|
| How | shares the central database connection | signed HTTP to the central application |
| Needs | credentials for the support database | a shared secret |
| Suits | applications you run, on one network | a satellite that should hold no database credentials |
| Can do | everything | the end-user side only |

```env
HELPDESK_DRIVER=api
HELPDESK_API_URL=https://support.example.com
HELPDESK_APP_KEY=app-a
HELPDESK_API_SECRET=a-long-random-string





```
Calling code does not change. The facade is the same and what comes back is still a `Ticket`, with its accessors, enum casts and `is*()` helpers intact.

```php
$ticket = HelpDesk::createTicket([...], $user);

$ticket->reference_number;  // 'HD-00042'
$ticket->isOpen();          // true
$ticket->requester_name;    // 'Ada Lovelace'





```
### What the signature proves

It proves **which application** is calling. The acting user is **asserted by** that application in the payload.

So a leaked secret can impersonate any user *of that application*, and none of another: the app key comes from the signed header and never from the body, and every read is scoped by it. HMAC gives authenticity and integrity, not confidentiality — **HTTPS is still required**.

```
canonical = METHOD \n REQUEST_URI \n TIMESTAMP \n NONCE \n sha256(RAW_BODY)
signature = "sha256=" + hex(hmac_sha256(canonical, secret))





```
The method and URI are signed, not just the body, so a captured request cannot be replayed against a different endpoint. A timestamp window and a single-use nonce are both required: a window alone leaves everything inside it replayable, a nonce alone lets a capture be replayed forever.

Secrets are a list per application, current first, so one rotates without a flag day.

### The identity work paid for itself

Nothing new was needed to represent a satellite's user. The snapshots added in v1.4.0–v1.6.0 already carry a name and email alongside the morph keys, which is exactly what the API payload sends — so events, history and notifications all work unchanged, and the central application names a requester whose model it has never seen.

### What the API driver cannot do

Operator actions throw immediately, naming what to use instead, rather than making a request that would be refused: updating, status changes, assignment, deletion, internal notes, watchers, and managing departments.

Models that come back over the wire have no database. Reading a relation the response did not carry throws with the relation named and the API call that replaces it, rather than a missing-table SQL error. A relation the response *did* carry is returned as normal.

### Attachments

A file travels base64 encoded inside the JSON body, so the signature covers it like any other payload.

That has a ceiling: the file grows by a third in transit and is held in memory on both ends, so `help-desk.api.max_inline_attachment` is deliberately smaller than `ticket.max_file_size`. Anything larger wants a short-lived signed upload URL, which this release does not implement.

The satellite has no access to the disk, so there is no URL to hand out. `getUrl()` throws rather than returning one that would 404 for its users, and `HelpDesk::attachments()->contents($attachment, $uuid)` fetches the bytes instead.

Extension and size limits are enforced on both ends — the satellite to avoid a wasted round trip, the central application because a satellite is not a trust boundary.

### Upgrading

```bash
composer update jeffersongoncalves/laravel-help-desk





```
No migration. No configuration change unless you want the new transport.

**Full Changelog**: https://github.com/jeffersongoncalves/laravel-help-desk/compare/v1.6.1...v1.7.0

## v1.6.1 - 2026-09-16

Documentation only. No API change, no migration — `composer update` is the whole upgrade.

### The Laravel Boost guidelines were three releases behind

`resources/boost/` ships inside the package, and Laravel Boost reads it from the consumer's `vendor/` directory. It had not been touched since February, so anyone installing v1.4.0 through v1.6.0 got guidelines describing the package as it was before any of them.

Worse than incomplete: it told an agent to read the polymorphic relations directly.

```php
$ticket->user        // fatal, not null, when applications share a database
$comment->author
$attachment->uploadedBy






```
Those are exactly the relations the identity snapshots added in v1.4.0–v1.6.0 exist to replace. Both guideline files now lead with that rule, because it is the one thing an agent must not get wrong.

### What the guidelines now cover

From v1.4.0 through v1.6.0:

- the dedicated database connection, and that satellite applications must not run the migrations
- `app.key` / `app.name` / `scope_to_app`, `Ticket::forApp()`, `app_key`, `app_name`
- identity snapshots on all five models, `notifyRequester()`, `toHelpDeskSnapshot()`
- the per-application morph alias requirement

Never covered at all, in any version:

- the attachment service — `store()`, `storeFromPath()`, `delete()`, both validation helpers, `getUrl()`, `getTemporaryUrl()`, `getFileSizeForHumans()`
- comment scopes and the four `is*()` helpers
- reading ticket history
- the exception list, including that `EmailProcessingException` never reaches the caller

Corrected: `AttachmentRemoved` takes `$removedBy`, and `InboundEmailReceived` and `InboundEmailProcessed` were missing from the events table.

### One untested claim, now tested

Both the README and the guidelines show `store($ticket, $file, $user, $comment)`, the four-argument form that ties an attachment to a comment. Nothing asserted it worked. The suite covers it now.

**Full Changelog**: https://github.com/jeffersongoncalves/laravel-help-desk/compare/v1.6.0...v1.6.1

## v1.6.0 - 2026-09-16

Closes the cross-application identity work: every polymorphic reference in the package now carries an identity snapshot. Additive — nothing existing changes behaviour.

### History performers and watchers carry a snapshot

`Ticket`, `TicketComment` and `TicketAttachment` already copied the person's name and email onto the row, so an application reading them without that model installed gets a name rather than a fatal `Class "..." not found`. `TicketHistory` and `TicketWatcher` did not.

```php
$entry->performer_name;
$entry->performer_email;
$entry->resolvedPerformer();  // the model, or null for a system action

$row->watcher_name;
$row->watcher_email;
$row->resolvedWatcher();







```
That makes five models with the same contract: prefer the live model, fall back to the copy, and never instantiate a class this application does not have.

Six of the nine history handlers hold the performer model and snapshot it directly. The other three — ticket created, comment added, attachment added — have only the stored morph keys, so they copy the snapshot from the row they are logging rather than loading the model back to rebuild it.

A system action, with no performer, writes no snapshot at all.

### The README documents the whole API

Auditing the README against the public surface turned up areas with no example at all. Now covered:

- **Attachments** — `store()`, `storeFromPath()`, `delete()`, the two validation helpers, `getUrl()`, `getTemporaryUrl()`, `getFileSizeForHumans()`
- **Exceptions** — which call throws which, and why inbound email failures are not among them
- **Ticket history** — reading it, and filtering by `HistoryAction`
- **Comment scopes** — `public()`, `internal()`, `replies()`, `notes()`, and the four `is*()` helpers
- **Ticket state** — `isOpen()`, `isClosed()`, `isResolved()`, `isAssigned()`, `isOverdue()`
- **Trait relations** — all six, where two were shown before
- `updateDepartment()` and `removeOperator()`

Every snippet has a test behind it, so a rename that breaks the documentation fails the suite instead of shipping.

### Upgrading

```bash
composer update jeffersongoncalves/laravel-help-desk
php artisan vendor:publish --tag=help-desk-migrations
php artisan migrate







```
One additive migration, `add_metadata_to_help_desk_ticket_watchers_table`: a nullable JSON column on `help_desk_ticket_watchers`. It is the only one of the five tables that had no `metadata` column.

Rows written before this release have no snapshot and return null from the accessors, the same forward-only behaviour v1.4.0 and v1.5.0 shipped with.

**Full Changelog**: https://github.com/jeffersongoncalves/laravel-help-desk/compare/v1.5.0...v1.6.0

## v1.5.0 - 2026-09-16

Completes the cross-application identity work started in v1.4.0. Additive: nothing existing changes behaviour, and there is no migration.

### Attachment uploaders carry an identity snapshot

`Ticket` and `TicketComment` already copied the requester's and the author's name and email onto the row, so an application reading them without that person's model installed gets a name rather than a fatal `Class "..." not found`. `TicketAttachment` did not, and nothing displayed the uploader — which is the only reason it had not surfaced.

```php
$attachment->uploader_name;        // the live model when it resolves, the copy when it does not
$attachment->uploader_email;
$attachment->resolvedUploadedBy(); // the model, or null when not installed here








```
`AttachmentService` writes the snapshot on both creation paths. The `metadata` column already existed, so no migration.

Rows written before this have no snapshot and return null from the accessors, matching how the requester snapshot shipped in v1.4.0.

### Known gap

`AttachmentService` is not the only writer. `jeffersongoncalves/filament-help-desk` creates `TicketAttachment` straight through the model in its user-facing create page, so attachments uploaded from that panel still get no snapshot.

Stamping it in the model's `creating` hook is not an option: the model holds only `uploaded_by_type` and `uploaded_by_id`, not the uploader instance to snapshot from, and loading it would be a query per attachment for a value the caller already has. `TicketAttachment::snapshotOf()` is public so that caller can pass the uploader it is already holding.

### Upgrading

```bash
composer update jeffersongoncalves/laravel-help-desk








```
Nothing else. No migration, no configuration change.

**Full Changelog**: https://github.com/jeffersongoncalves/laravel-help-desk/compare/v1.4.1...v1.5.0

## v1.4.1 - 2026-09-16

Repository and packaging fixes. No change to the library itself — `git diff v1.4.0..v1.4.1 -- src/ config/ database/` is empty, so upgrading is safe and requires nothing.

### The published package no longer ships its own test suite

There was no `.gitattributes`, so `composer require` pulled `tests/`, `.github/`, `art/` and the tooling configs into every consumer's vendor directory. They are now `export-ignore`d.

The same file adds `* text=auto eol=lf`. Without it a Windows checkout gets CRLF and every Pint run reports a `line_ending` fixer on roughly a hundred files, drowning real findings.

### Changelog, and the automation behind it

`CHANGELOG.md` now exists, and `.github/workflows/update-changelog.yml` appends each release to it. This release is the first the workflow handles.

Two problems were found in that workflow during review and fixed before it ever ran:

- The release tag was interpolated straight into a `run:` block, so a crafted tag could inject shell commands with the job's `GITHUB_TOKEN` (CWE-78). It now passes through `env:`.
- A release can target a full commit SHA, which was handed to `git-auto-commit-action` as a branch name. The workflow now verifies the target is a branch and fails with a clear message when it is not, rather than pushing somewhere the release never named.

### Also in this release

- `.github/dependabot.yml` — weekly, grouped per ecosystem, 7-day cooldown, no auto-merge
- `.github/CONTRIBUTING.md` and `.github/SECURITY.md`, the latter so vulnerabilities have somewhere to go other than the public issue tracker
- `pint.json` pinning the `laravel` preset explicitly
- README links to the changelog and contributing guide now point at the repository, since both are excluded from the published archive

### Test suite

The suite left package settings behind between tests. Testbench reuses the application between some tests, so a leaked `help-desk.connection` reached the next test's migrations — which is why MySQL and Postgres failed while SQLite passed. The alternate connection is now defined once in the test harness, and the settings a test writes are reset after every Feature test.

That reset also turned out never to have run: a standalone `afterEach()` in `tests/Pest.php` does not fire under Pest 4.7.8. It is now chained onto `uses()`, verified by probe.

**Full Changelog**: https://github.com/jeffersongoncalves/laravel-help-desk/compare/v1.4.0...v1.4.1
