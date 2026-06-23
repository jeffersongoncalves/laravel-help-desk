<?php

use Illuminate\Support\Facades\Event;
use JeffersonGoncalves\HelpDesk\Events\InboundEmailReceived;
use JeffersonGoncalves\HelpDesk\Models\InboundEmail;
use JeffersonGoncalves\HelpDesk\Services\InboundEmailService;

beforeEach(function () {
    $this->service = app(InboundEmailService::class);
});

function inboundData(array $overrides = []): array
{
    return array_merge([
        'message_id' => '<unique@example.com>',
        'from_address' => 'sender@example.com',
        'to_addresses' => ['support@example.com'],
        'subject' => 'Subject',
        'text_body' => 'Body',
        'status' => 'pending',
    ], $overrides);
}

it('stores an inbound email and dispatches an event', function () {
    Event::fake([InboundEmailReceived::class]);

    $email = $this->service->store(inboundData());

    expect($email->message_id)->toBe('<unique@example.com>')
        ->and($email->status)->toBe('pending');

    Event::assertDispatched(InboundEmailReceived::class);
});

it('does not store duplicate emails with the same message id', function () {
    Event::fake([InboundEmailReceived::class]);

    $first = $this->service->store(inboundData());
    $second = $this->service->store(inboundData(['subject' => 'Different']));

    expect($second->id)->toBe($first->id)
        ->and(InboundEmail::count())->toBe(1);

    Event::assertDispatchedTimes(InboundEmailReceived::class, 1);
});

it('marks an email as processed', function () {
    $email = InboundEmail::factory()->create();

    $this->service->markProcessed($email, 5, 7);

    expect($email->fresh())
        ->status->toBe('processed')
        ->ticket_id->toBe(5)
        ->comment_id->toBe(7);
});

it('marks an email as failed', function () {
    $email = InboundEmail::factory()->create();

    $this->service->markFailed($email, 'Something broke');

    expect($email->fresh())
        ->status->toBe('failed')
        ->error_message->toBe('Something broke');
});

it('marks an email as ignored', function () {
    $email = InboundEmail::factory()->create();

    $this->service->markIgnored($email);

    expect($email->fresh()->status)->toBe('ignored');
});

it('cleans old processed emails', function () {
    InboundEmail::factory()->processed()->create(['created_at' => now()->subDays(60)]);
    InboundEmail::factory()->ignored()->create(['created_at' => now()->subDays(60)]);
    InboundEmail::factory()->processed()->create(['created_at' => now()->subDay()]);
    InboundEmail::factory()->failed()->create(['created_at' => now()->subDays(60)]);

    $deleted = $this->service->cleanOldEmails(30);

    expect($deleted)->toBe(2)
        ->and(InboundEmail::count())->toBe(2);
});
