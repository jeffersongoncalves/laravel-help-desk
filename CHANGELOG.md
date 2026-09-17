# Changelog

All notable changes to `laravel-help-desk` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Entries are appended automatically on release. For versions up to and including
[v1.4.0](https://github.com/jeffersongoncalves/laravel-help-desk/releases/tag/v1.4.0),
released before this file existed, see the
[releases page](https://github.com/jeffersongoncalves/laravel-help-desk/releases).

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
