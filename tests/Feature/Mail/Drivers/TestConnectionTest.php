<?php

use JeffersonGoncalves\HelpDesk\Exceptions\EmailProcessingException;
use JeffersonGoncalves\HelpDesk\Mail\Drivers\ImapDriver;
use JeffersonGoncalves\HelpDesk\Mail\Drivers\MailgunDriver;
use JeffersonGoncalves\HelpDesk\Mail\Drivers\PostmarkDriver;
use JeffersonGoncalves\HelpDesk\Mail\Drivers\ResendDriver;
use JeffersonGoncalves\HelpDesk\Mail\Drivers\SendGridDriver;
use JeffersonGoncalves\HelpDesk\Models\EmailChannel;

it('reports success without a live check for every webhook-based driver', function (string $driverClass) {
    $channel = EmailChannel::factory()->make(['driver' => (new $driverClass)->getDriverName()]);

    $result = (new $driverClass)->testConnection($channel);

    expect($result)->toBe([
        'success' => true,
        'message' => 'Webhook-based driver — nothing to test until a webhook is received.',
    ]);
})->with([
    SendGridDriver::class,
    PostmarkDriver::class,
    MailgunDriver::class,
    ResendDriver::class,
]);

it('throws the same missing-dependency exception poll() throws, since webklex/php-imap is not installed here', function () {
    $channel = EmailChannel::factory()->make(['driver' => 'imap']);

    expect(fn () => (new ImapDriver)->testConnection($channel))
        ->toThrow(EmailProcessingException::class);
});
