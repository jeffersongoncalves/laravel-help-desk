<?php

use JeffersonGoncalves\HelpDesk\Events\InboundEmailReceived;
use JeffersonGoncalves\HelpDesk\Listeners\ProcessInboundEmail;
use JeffersonGoncalves\HelpDesk\Models\Department;
use JeffersonGoncalves\HelpDesk\Models\InboundEmail;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Services\TicketService;
use JeffersonGoncalves\HelpDesk\Tests\TestUser;

beforeEach(function () {
    $this->listener = app(ProcessInboundEmail::class);
    $this->department = Department::factory()->create(['is_active' => true]);
    $this->user = TestUser::create(['name' => 'Sender', 'email' => 'sender@example.com']);
});

function processEmail(InboundEmail $email): void
{
    app(ProcessInboundEmail::class)->handle(new InboundEmailReceived($email));
}

it('creates a new ticket from an inbound email', function () {
    $email = InboundEmail::factory()->create([
        'from_address' => 'sender@example.com',
        'subject' => 'I need help',
        'text_body' => 'Please assist me.',
    ]);

    processEmail($email);

    expect(Ticket::count())->toBe(1);

    $ticket = Ticket::first();

    expect($ticket->title)->toBe('I need help')
        ->and($ticket->source)->toBe('email')
        ->and($ticket->department_id)->toBe($this->department->id)
        ->and($email->fresh()->status)->toBe('processed')
        ->and($email->fresh()->ticket_id)->toBe($ticket->id);
});

it('adds a reply to an existing ticket matched via in-reply-to', function () {
    $ticket = app(TicketService::class)->create([
        'title' => 'Original ticket',
        'description' => 'Original body',
        'department_id' => $this->department->id,
        'email_message_id' => '<original@example.com>',
    ], $this->user);

    $email = InboundEmail::factory()->create([
        'from_address' => 'sender@example.com',
        'in_reply_to' => '<original@example.com>',
        'subject' => 'Re: Original ticket',
        'text_body' => 'Here is my reply.',
    ]);

    processEmail($email);

    expect(Ticket::count())->toBe(1)
        ->and($ticket->fresh()->comments)->toHaveCount(1)
        ->and($ticket->fresh()->comments->first()->body)->toBe('Here is my reply.')
        ->and($email->fresh()->status)->toBe('processed');
});

it('stores an email body as escaped text, never as markup', function (array $body, string $expected) {
    $ticket = app(TicketService::class)->create([
        'title' => 'Original ticket',
        'description' => 'Original body',
        'department_id' => $this->department->id,
        'email_message_id' => '<original@example.com>',
    ], $this->user);

    $email = InboundEmail::factory()->create([
        'from_address' => 'sender@example.com',
        'in_reply_to' => '<original@example.com>',
        'subject' => 'Re: Original ticket',
        'text_body' => null,
        'html_body' => null,
        ...$body,
    ]);

    processEmail($email);

    expect($ticket->fresh()->comments->first()->body)->toBe($expected);
})->with([
    'plain text' => [
        ['text_body' => "a < b\n<script>alert(1)</script><img src=x onerror=alert(2)>"],
        'a &lt; b<br>'."\n".'&lt;script&gt;alert(1)&lt;/script&gt;&lt;img src=x onerror=alert(2)&gt;',
    ],
    'html only' => [
        ['html_body' => '<p>Hi &lt;script&gt;alert(1)&lt;/script&gt;</p><script>alert(2)</script>'],
        'Hi &lt;script&gt;alert(1)&lt;/script&gt;alert(2)',
    ],
]);

it('escapes the email body of a new ticket', function () {
    $email = InboundEmail::factory()->create([
        'from_address' => 'sender@example.com',
        'subject' => 'I need help',
        'text_body' => '<img src=x onerror=alert(1)>',
    ]);

    processEmail($email);

    expect(Ticket::first()->description)->toBe('&lt;img src=x onerror=alert(1)&gt;');
});

it('marks the email as failed when no user matches the sender', function () {
    $email = InboundEmail::factory()->create([
        'from_address' => 'unknown@example.com',
        'subject' => 'Help',
        'text_body' => 'Body',
    ]);

    processEmail($email);

    expect(Ticket::count())->toBe(0)
        ->and($email->fresh()->status)->toBe('failed');
});

it('skips emails that are not pending', function () {
    $email = InboundEmail::factory()->processed()->create([
        'from_address' => 'sender@example.com',
    ]);

    processEmail($email);

    expect(Ticket::count())->toBe(0);
});
