<?php

use JeffersonGoncalves\HelpDesk\Mail\EmailParser;

beforeEach(function () {
    $this->parser = new EmailParser;
});

it('extracts basic fields from email data', function () {
    $result = $this->parser->parse([
        'message_id' => '<test@example.com>',
        'from' => 'John Doe <john@example.com>',
        'to' => 'support@example.com',
        'subject' => 'Test Subject',
        'text_body' => 'Hello, I need help.',
    ]);

    expect($result['message_id'])->toBe('<test@example.com>')
        ->and($result['from_address'])->toBe('john@example.com')
        ->and($result['from_name'])->toBe('John Doe')
        ->and($result['to_addresses'])->toBe(['support@example.com'])
        ->and($result['subject'])->toBe('Test Subject')
        ->and($result['text_body'])->toBe('Hello, I need help.');
});

it('handles plain email address without name', function () {
    $result = $this->parser->parse([
        'from' => 'john@example.com',
        'to' => 'support@example.com',
    ]);

    expect($result['from_address'])->toBe('john@example.com')
        ->and($result['from_name'])->toBeNull();
});

it('handles multiple to addresses', function () {
    $result = $this->parser->parse([
        'from' => 'john@example.com',
        'to' => 'support@example.com, admin@example.com',
    ]);

    expect($result['to_addresses'])
        ->toHaveCount(2)
        ->sequence(
            fn ($item) => $item->toBe('support@example.com'),
            fn ($item) => $item->toBe('admin@example.com'),
        );
});

it('removes quoted reply from text body', function () {
    $body = "Thanks for the help.\n\nOn Mon, Jan 1, 2024 John Doe wrote:\n> Previous message";

    expect($this->parser->cleanTextBody($body))->toBe('Thanks for the help.');
});

it('generates message id when missing', function () {
    $result = $this->parser->parse([
        'from' => 'john@example.com',
        'to' => 'support@example.com',
    ]);

    expect($result['message_id'])
        ->toStartWith('<helpdesk-')
        ->toEndWith('@generated>');
});

it('handles in_reply_to and references headers', function () {
    $result = $this->parser->parse([
        'from' => 'john@example.com',
        'to' => 'support@example.com',
        'in_reply_to' => '<original@example.com>',
        'references' => '<ref1@example.com> <ref2@example.com>',
    ]);

    expect($result['in_reply_to'])->toBe('<original@example.com>')
        ->and($result['references'])->toBe('<ref1@example.com> <ref2@example.com>');
});
