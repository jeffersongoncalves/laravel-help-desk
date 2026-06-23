<?php

use Illuminate\Http\Request;
use JeffersonGoncalves\HelpDesk\Http\Middleware\VerifyResendSignature;
use Symfony\Component\HttpFoundation\Response;

function resendRequest(string $payload, array $headers): Request
{
    $request = Request::create('/help-desk/webhooks/resend', 'POST', [], [], [], [], $payload);

    foreach ($headers as $key => $value) {
        $request->headers->set($key, $value);
    }

    return $request;
}

function resendSignature(string $secret, string $svixId, string $svixTimestamp, string $payload): string
{
    $secretBytes = base64_decode(str_starts_with($secret, 'whsec_') ? substr($secret, 6) : $secret);
    $signedContent = "{$svixId}.{$svixTimestamp}.{$payload}";

    return base64_encode(hash_hmac('sha256', $signedContent, $secretBytes, true));
}

beforeEach(function () {
    $this->secret = 'whsec_'.base64_encode('raw-secret-bytes');
    $this->middleware = new VerifyResendSignature;
    $this->next = fn (Request $request) => new Response('passed', 200);
});

it('passes a request with a valid svix signature', function () {
    config()->set('help-desk.email.inbound.resend.webhook_secret', $this->secret);

    $payload = '{"type":"email.received"}';
    $svixId = 'msg_123';
    $svixTimestamp = (string) time();
    $signature = resendSignature($this->secret, $svixId, $svixTimestamp, $payload);

    $response = $this->middleware->handle(resendRequest($payload, [
        'svix-id' => $svixId,
        'svix-timestamp' => $svixTimestamp,
        'svix-signature' => "v1,{$signature}",
    ]), $this->next);

    expect($response->getStatusCode())->toBe(200);
});

it('rejects a request with an invalid signature', function () {
    config()->set('help-desk.email.inbound.resend.webhook_secret', $this->secret);

    assertAbortsWith(fn () => $this->middleware->handle(resendRequest('{"type":"email.received"}', [
        'svix-id' => 'msg_123',
        'svix-timestamp' => (string) time(),
        'svix-signature' => 'v1,not-a-valid-signature',
    ]), $this->next), 403);
});

it('rejects a request with missing svix headers', function () {
    config()->set('help-desk.email.inbound.resend.webhook_secret', $this->secret);

    assertAbortsWith(
        fn () => $this->middleware->handle(resendRequest('{}', []), $this->next),
        403
    );
});

it('fails closed when the webhook secret is not configured', function () {
    config()->set('help-desk.email.inbound.resend.webhook_secret', null);

    assertAbortsWith(fn () => $this->middleware->handle(resendRequest('{}', [
        'svix-id' => 'msg_123',
        'svix-timestamp' => (string) time(),
        'svix-signature' => 'v1,whatever',
    ]), $this->next), 403);
});
