<?php

use Illuminate\Http\Request;
use JeffersonGoncalves\HelpDesk\Http\Middleware\VerifyMailgunSignature;
use Symfony\Component\HttpFoundation\Response;

function mailgunRequest(string $timestamp, string $token, string $signature): Request
{
    return Request::create('/help-desk/webhooks/mailgun', 'POST', [
        'timestamp' => $timestamp,
        'token' => $token,
        'signature' => $signature,
    ]);
}

beforeEach(function () {
    $this->middleware = new VerifyMailgunSignature;
    $this->next = fn (Request $request) => new Response('passed', 200);
});

it('passes a request with a valid signature', function () {
    config()->set('help-desk.email.inbound.mailgun.signing_key', 'secret-key');

    $timestamp = (string) time();
    $token = 'token-value';
    $signature = hash_hmac('sha256', $timestamp.$token, 'secret-key');

    $response = $this->middleware->handle(mailgunRequest($timestamp, $token, $signature), $this->next);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toBe('passed');
});

it('rejects a request with an invalid signature', function () {
    config()->set('help-desk.email.inbound.mailgun.signing_key', 'secret-key');

    assertAbortsWith(
        fn () => $this->middleware->handle(mailgunRequest((string) time(), 'token', 'wrong-signature'), $this->next),
        403
    );
});

it('rejects a request with missing signature fields', function () {
    config()->set('help-desk.email.inbound.mailgun.signing_key', 'secret-key');

    assertAbortsWith(
        fn () => $this->middleware->handle(mailgunRequest('', '', ''), $this->next),
        403
    );
});

it('fails closed when the signing key is not configured', function () {
    config()->set('help-desk.email.inbound.mailgun.signing_key', null);

    assertAbortsWith(
        fn () => $this->middleware->handle(mailgunRequest((string) time(), 'token', 'sig'), $this->next),
        403
    );
});
