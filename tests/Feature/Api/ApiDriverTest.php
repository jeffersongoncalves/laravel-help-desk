<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use JeffersonGoncalves\HelpDesk\Api\HelpDeskSignature;
use JeffersonGoncalves\HelpDesk\Api\Models\ApiTicket;
use JeffersonGoncalves\HelpDesk\Api\Models\ApiTicketAttachment;
use JeffersonGoncalves\HelpDesk\Api\Repositories\ApiTicketRepository;
use JeffersonGoncalves\HelpDesk\Contracts\TicketRepository;
use JeffersonGoncalves\HelpDesk\Enums\TicketStatus;
use JeffersonGoncalves\HelpDesk\Exceptions\HelpDeskApiException;
use JeffersonGoncalves\HelpDesk\Exceptions\TicketNotFoundException;
use JeffersonGoncalves\HelpDesk\Facades\HelpDesk;
use JeffersonGoncalves\HelpDesk\Tests\TestUser;

beforeEach(function () {
    config()->set('help-desk.driver', 'api');
    config()->set('help-desk.app.key', 'app-a');
    config()->set('help-desk.api.url', 'https://support.example.com');
    config()->set('help-desk.api.secret', 'app-a-secret');
});

function ticketPayload(array $overrides = []): array
{
    return ['data' => array_merge([
        'uuid' => '550e8400-e29b-41d4-a716-446655440000',
        'reference_number' => 'HD-00042',
        'department_id' => 1,
        'category_id' => null,
        'title' => 'Printer offline',
        'description' => 'It stopped printing.',
        'status' => 'open',
        'priority' => 'medium',
        'source' => 'api',
        'app_key' => 'app-a',
        'requester_name' => 'Ada Lovelace',
        'requester_email' => 'ada@example.com',
        'closed_at' => null,
        'due_at' => null,
        'last_replied_at' => null,
        'created_at' => '2026-09-17T00:00:00+00:00',
        'updated_at' => '2026-09-17T00:00:00+00:00',
    ], $overrides)];
}

function actorUser(): TestUser
{
    return TestUser::create(['name' => 'Ada Lovelace', 'email' => Str::random(8).'@example.com']);
}

it('binds the API implementations when the driver is api', function () {
    expect(app(TicketRepository::class))
        ->toBeInstanceOf(ApiTicketRepository::class);
});

it('creates a ticket over the wire and hydrates a usable model', function () {
    Http::fake(['*' => Http::response(ticketPayload(), 201)]);

    $ticket = HelpDesk::createTicket([
        'department_id' => 1,
        'title' => 'Printer offline',
        'description' => 'It stopped printing.',
    ], actorUser());

    expect($ticket)->toBeInstanceOf(ApiTicket::class)
        ->and($ticket->reference_number)->toBe('HD-00042')
        ->and($ticket->status)->toBe(TicketStatus::Open)
        ->and($ticket->isOpen())->toBeTrue()
        ->and($ticket->requester_name)->toBe('Ada Lovelace')
        ->and($ticket->exists)->toBeTrue()
        ->and($ticket->isDirty())->toBeFalse();
});

it('signs every request it sends', function () {
    Http::fake(['*' => Http::response(ticketPayload(), 201)]);

    HelpDesk::createTicket(['department_id' => 1, 'title' => 'x', 'description' => 'y'], actorUser());

    Http::assertSent(function ($request) {
        return $request->hasHeader(HelpDeskSignature::APP_HEADER, 'app-a')
            && $request->hasHeader(HelpDeskSignature::TIMESTAMP_HEADER)
            && $request->hasHeader(HelpDeskSignature::NONCE_HEADER)
            && str_starts_with($request->header(HelpDeskSignature::SIGNATURE_HEADER)[0], 'sha256=')
            && $request->url() === 'https://support.example.com/help-desk/api/tickets';
    });
});

it('sends the actor with its identity snapshot', function () {
    Http::fake(['*' => Http::response(ticketPayload(), 201)]);

    HelpDesk::createTicket(['department_id' => 1, 'title' => 'x', 'description' => 'y'], actorUser());

    Http::assertSent(function ($request) {
        // The body is sent raw so the signature covers exactly these bytes,
        // so read it raw here too rather than through data().
        $actor = json_decode($request->body(), true)['actor'];

        return $actor['name'] === 'Ada Lovelace'
            && str_ends_with($actor['email'], '@example.com')
            && $actor['type'] === TestUser::class;
    });
});

it('throws something readable when a relation was never sent', function () {
    Http::fake(['*' => Http::response(ticketPayload(), 201)]);

    $ticket = HelpDesk::createTicket(['department_id' => 1, 'title' => 'x', 'description' => 'y'], actorUser());

    expect(fn () => $ticket->comments)
        ->toThrow(
            HelpDeskApiException::class,
            'Relation [comments] on ApiTicket needs the database driver.',
        );

    // And it names what to do instead.
    try {
        $ticket->comments;
    } catch (HelpDeskApiException $e) {
        expect($e->getMessage())->toContain('HelpDesk::tickets()->findByUuid($uuid)');
    }
});

it('returns a relation the response did fill', function () {
    Http::fake(['*' => Http::response(ticketPayload([
        'comments' => [[
            'id' => 1,
            'body' => 'Any news?',
            'type' => 'reply',
            'author_name' => 'Ada Lovelace',
            'created_at' => '2026-09-17T00:00:00+00:00',
        ]],
    ]), 200)]);

    // A read has no actor argument, so it uses the authenticated user.
    test()->actingAs(actorUser());

    $ticket = app(TicketRepository::class)->findByUuid('550e8400-e29b-41d4-a716-446655440000');

    expect($ticket->comments)->toHaveCount(1)
        ->and($ticket->comments->first()->body)->toBe('Any news?')
        ->and($ticket->comments->first()->author_name)->toBe('Ada Lovelace');
});

it('keys a hydrated ticket by its uuid', function () {
    // Nothing else catches this: a null key is only visible once a caller
    // keys a collection by it, and then it silently loses rows.
    Http::fake(['*' => Http::response([
        'data' => [
            ticketPayload()['data'],
            ticketPayload(['uuid' => '6ba7b810-9dad-11d1-80b4-00c04fd430c8'])['data'],
        ],
    ], 200)]);

    $tickets = app(TicketRepository::class)->forActor(actorUser())->getCollection();

    expect($tickets->first()->getKey())->toBe('550e8400-e29b-41d4-a716-446655440000')
        ->and($tickets->last()->getKey())->toBe('6ba7b810-9dad-11d1-80b4-00c04fd430c8')
        ->and($tickets->keyBy->getKey())->toHaveCount(2);
});

it('sends the filters as query parameters', function () {
    Http::fake(['*' => Http::response(['data' => [], 'meta' => ['total' => 0]], 200)]);

    app(TicketRepository::class)->forActor(
        actorUser(),
        status: ['open', TicketStatus::Pending],
        priority: 'urgent',
        search: 'scanner',
        sort: 'last_replied_at',
        direction: 'asc',
    );

    Http::assertSent(function ($request) {
        $query = [];
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return $query['status'] === ['open', 'pending']
            && $query['priority'] === ['urgent']
            && $query['q'] === 'scanner'
            && $query['sort'] === 'last_replied_at'
            && $query['direction'] === 'asc';
    });
});

it('refuses a sort the central application would refuse, before sending it', function () {
    Http::fake();

    // The same exception the database driver raises, so a panel written
    // against the contract behaves the same on either transport.
    expect(fn () => app(TicketRepository::class)->forActor(actorUser(), sort: 'user_id'))
        ->toThrow(InvalidArgumentException::class, 'Tickets cannot be sorted by [user_id].');

    Http::assertNothingSent();
});

it('hydrates the attachments the show response carried', function () {
    Http::fake(['*' => Http::response(ticketPayload([
        'comments' => [[
            'id' => 7,
            'body' => 'Here is the log.',
            'type' => 'reply',
            'author_name' => 'Ada Lovelace',
            'created_at' => '2026-09-17T00:00:00+00:00',
        ]],
        'attachments' => [
            [
                'uuid' => 'aaaaaaaa-0000-0000-0000-000000000001',
                'comment_id' => 7,
                'file_name' => 'printer.log',
                'mime_type' => 'text/plain',
                'file_size' => 120,
                'uploader_name' => 'Ada Lovelace',
                'created_at' => '2026-09-17T00:00:00+00:00',
            ],
            [
                'uuid' => 'aaaaaaaa-0000-0000-0000-000000000002',
                'comment_id' => null,
                'file_name' => 'photo.png',
                'mime_type' => 'image/png',
                'file_size' => 2048,
                'uploader_name' => 'Ada Lovelace',
                'created_at' => '2026-09-17T00:00:00+00:00',
            ],
        ],
    ]), 200)]);

    test()->actingAs(actorUser());

    $ticket = app(TicketRepository::class)->findByUuid('550e8400-e29b-41d4-a716-446655440000');

    expect($ticket->attachments)->toHaveCount(2)
        ->and($ticket->attachments->first())->toBeInstanceOf(ApiTicketAttachment::class)
        // The failure this replaces: an array, so reading a property on it
        // threw "attempt to read property on array" in the consumer's blade.
        ->and($ticket->attachments->first()->file_name)->toBe('printer.log')
        ->and($ticket->attachments->first()->getKey())->toBe('aaaaaaaa-0000-0000-0000-000000000001')
        // The flat list carries comment_id, so the comment's own subset comes
        // from it — TicketCommentResource does not nest them.
        ->and($ticket->comments->first()->attachments)->toHaveCount(1)
        ->and($ticket->comments->first()->attachments->first()->file_name)->toBe('printer.log');
});

it('leaves attachments unset when the response did not carry them', function () {
    Http::fake(['*' => Http::response(ticketPayload(), 201)]);

    $ticket = HelpDesk::createTicket(['department_id' => 1, 'title' => 'x', 'description' => 'y'], actorUser());

    expect(fn () => $ticket->attachments)
        ->toThrow(
            HelpDeskApiException::class,
            'Relation [attachments] on ApiTicket needs the database driver.',
        );
});

it('refuses to save a hydrated model', function () {
    Http::fake(['*' => Http::response(ticketPayload(), 201)]);

    $ticket = HelpDesk::createTicket(['department_id' => 1, 'title' => 'x', 'description' => 'y'], actorUser());

    expect(fn () => $ticket->save())->toThrow(HelpDeskApiException::class);
});

it('refuses an operator action without making a request', function () {
    Http::fake();

    $ticket = new ApiTicket;

    // Closing and reopening are the requester's own; the other four statuses
    // are not, and are refused before a request is made.
    expect(fn () => HelpDesk::changeStatus($ticket, TicketStatus::Resolved, actorUser()))
        ->toThrow(HelpDeskApiException::class, 'Changing a ticket to resolved is an operator action');

    expect(fn () => HelpDesk::assignTicket($ticket, actorUser()))
        ->toThrow(HelpDeskApiException::class);

    expect(fn () => HelpDesk::addNote($ticket, actorUser(), 'internal'))
        ->toThrow(HelpDeskApiException::class, 'addNote() is an operator action');

    Http::assertNothingSent();
});

it('maps a rejected signature to a message naming what to check', function () {
    test()->actingAs(actorUser());
    Http::fake(['*' => Http::response(['message' => 'Invalid signature.'], 401)]);

    expect(fn () => app(TicketRepository::class)->findByUuid('x'))
        ->toThrow(HelpDeskApiException::class, 'rejected the signature');
});

it('maps a 404 to the same exception the database driver throws', function () {
    // The point of the seam: findByUuid() behaves identically on both drivers.
    test()->actingAs(actorUser());
    Http::fake(['*' => Http::response([], 404)]);

    expect(fn () => app(TicketRepository::class)->findByUuid('x'))
        ->toThrow(TicketNotFoundException::class);
});

it('surfaces the validation message the central application sent', function () {
    Http::fake(['*' => Http::response(['errors' => ['title' => ['The title field is required.']]], 422)]);

    expect(fn () => HelpDesk::createTicket(['department_id' => 1], actorUser()))
        ->toThrow(HelpDeskApiException::class, 'The title field is required.');
});

it('reports an unexpected status with its code', function () {
    test()->actingAs(actorUser());
    Http::fake(['*' => Http::response('boom', 500)]);

    expect(fn () => app(TicketRepository::class)->findByUuid('x'))
        ->toThrow(HelpDeskApiException::class, 'returned 500');
});

it('reports a connection failure as unreachable', function () {
    test()->actingAs(actorUser());
    Http::fake(fn () => throw new ConnectionException('timed out'));

    expect(fn () => app(TicketRepository::class)->findByUuid('x'))
        ->toThrow(HelpDeskApiException::class, 'Could not reach the central help desk');
});

it('never retries a write', function () {
    Http::fake(['*' => Http::response(ticketPayload(), 201)]);

    HelpDesk::createTicket(['department_id' => 1, 'title' => 'x', 'description' => 'y'], actorUser());

    // A retried write could duplicate a ticket the server already committed.
    Http::assertSentCount(1);
});

it('names the missing key rather than sending an unconfigured request', function () {
    config()->set('help-desk.api.url', null);

    Http::fake();

    expect(fn () => HelpDesk::createTicket(['department_id' => 1], actorUser()))
        ->toThrow(HelpDeskApiException::class, 'help-desk.api.url');

    Http::assertNothingSent();
});

it('says who is missing when a read has no actor', function () {
    Http::fake();

    expect(fn () => app(TicketRepository::class)->findByUuid('x'))
        ->toThrow(HelpDeskApiException::class, 'could not tell who is acting');

    Http::assertNothingSent();
});
