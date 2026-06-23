<?php

use Illuminate\Support\Facades\Http;
use JeffersonGoncalves\HelpDesk\Mail\Drivers\MailgunDriver;
use JeffersonGoncalves\HelpDesk\Mail\Drivers\PostmarkDriver;
use JeffersonGoncalves\HelpDesk\Mail\Drivers\ResendDriver;
use JeffersonGoncalves\HelpDesk\Mail\Drivers\SendGridDriver;

it('parses a mailgun webhook payload', function () {
    $parsed = (new MailgunDriver)->parseWebhookPayload([
        'Message-Id' => '<abc@mg>',
        'In-Reply-To' => '<reply@mg>',
        'sender' => 'sender@example.com',
        'recipient' => 'support@example.com',
        'subject' => 'Help needed',
        'body-plain' => 'Plain body',
        'body-html' => '<p>HTML body</p>',
    ]);

    expect($parsed)
        ->message_id->toBe('<abc@mg>')
        ->in_reply_to->toBe('<reply@mg>')
        ->from->toBe('sender@example.com')
        ->subject->toBe('Help needed')
        ->text_body->toBe('Plain body')
        ->html_body->toBe('<p>HTML body</p>')
        ->and($parsed['to_addresses'])->toBe(['support@example.com']);

    expect((new MailgunDriver)->getDriverName())->toBe('mailgun');
});

it('parses a sendgrid webhook payload', function () {
    $parsed = (new SendGridDriver)->parseWebhookPayload([
        'headers' => "Message-ID: <abc@sg>\nIn-Reply-To: <reply@sg>\nReferences: <ref@sg>",
        'envelope' => json_encode(['to' => ['support@example.com']]),
        'from' => 'Sender <sender@example.com>',
        'subject' => 'Subject line',
        'text' => 'Body text',
        'html' => '<p>Body</p>',
    ]);

    expect($parsed)
        ->message_id->toBe('<abc@sg>')
        ->in_reply_to->toBe('<reply@sg>')
        ->references->toBe('<ref@sg>')
        ->subject->toBe('Subject line')
        ->text_body->toBe('Body text')
        ->and($parsed['to_addresses'])->toBe(['support@example.com']);

    expect((new SendGridDriver)->getDriverName())->toBe('sendgrid');
});

it('parses a postmark webhook payload', function () {
    $parsed = (new PostmarkDriver)->parseWebhookPayload([
        'MessageID' => 'pm-123',
        'FromFull' => ['Email' => 'sender@example.com', 'Name' => 'Sender Name'],
        'ToFull' => [['Email' => 'support@example.com']],
        'CcFull' => [['Email' => 'cc@example.com']],
        'Subject' => 'A subject',
        'TextBody' => 'Text body',
        'HtmlBody' => '<p>HTML</p>',
        'StrippedTextReply' => 'Just the reply',
        'Headers' => [
            ['Name' => 'In-Reply-To', 'Value' => '<reply@pm>'],
            ['Name' => 'References', 'Value' => '<ref@pm>'],
        ],
        'Attachments' => [
            ['Name' => 'file.pdf', 'Content' => 'base64data', 'ContentType' => 'application/pdf', 'ContentLength' => 10],
        ],
    ]);

    expect($parsed)
        ->message_id->toBe('pm-123')
        ->from->toBe('sender@example.com')
        ->from_name->toBe('Sender Name')
        ->in_reply_to->toBe('<reply@pm>')
        ->references->toBe('<ref@pm>')
        ->stripped_reply->toBe('Just the reply')
        ->and($parsed['to_addresses'])->toBe(['support@example.com'])
        ->and($parsed['cc_addresses'])->toBe(['cc@example.com'])
        ->and($parsed['attachments'])->toHaveCount(1)
        ->and($parsed['attachments'][0]['filename'])->toBe('file.pdf');

    expect((new PostmarkDriver)->getDriverName())->toBe('postmark');
});

it('parses a resend webhook payload from the data wrapper', function () {
    $parsed = (new ResendDriver)->parseWebhookPayload([
        'type' => 'email.received',
        'data' => [
            'message_id' => '<abc@resend>',
            'email_id' => 'email_123',
            'from' => 'sender@example.com',
            'to' => ['support@example.com'],
            'subject' => 'Resend subject',
        ],
    ]);

    expect($parsed)
        ->message_id->toBe('<abc@resend>')
        ->email_id->toBe('email_123')
        ->from->toBe('sender@example.com')
        ->subject->toBe('Resend subject')
        ->text_body->toBeNull()
        ->and($parsed['to_addresses'])->toBe(['support@example.com']);
});

it('fetches resend email content via the api', function () {
    Http::fake([
        'api.resend.com/emails/email_123' => Http::response([
            'text' => 'Fetched text body',
            'html' => '<p>Fetched HTML</p>',
            'headers' => [
                ['name' => 'In-Reply-To', 'value' => '<reply@resend>'],
            ],
        ], 200),
    ]);

    $content = (new ResendDriver)->fetchEmailContent('email_123', 're_test_key');

    expect($content)
        ->text_body->toBe('Fetched text body')
        ->html_body->toBe('<p>Fetched HTML</p>')
        ->in_reply_to->toBe('<reply@resend>');
});

it('verifies a valid resend svix signature', function () {
    $rawSecret = 'raw-secret-bytes';
    $secret = 'whsec_'.base64_encode($rawSecret);
    $svixId = 'msg_1';
    $svixTimestamp = (string) time();
    $payload = '{"type":"email.received"}';

    $secretBytes = $rawSecret;
    $signedContent = "{$svixId}.{$svixTimestamp}.{$payload}";
    $signature = base64_encode(hash_hmac('sha256', $signedContent, $secretBytes, true));

    $valid = (new ResendDriver)->verifyWebhookSignature($payload, [
        'svix-id' => $svixId,
        'svix-timestamp' => $svixTimestamp,
        'svix-signature' => "v1,{$signature}",
    ], $secret);

    expect($valid)->toBeTrue();
});
