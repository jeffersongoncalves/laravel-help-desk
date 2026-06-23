<?php

use Illuminate\Http\Request;
use JeffersonGoncalves\HelpDesk\Http\Middleware\VerifySendGridSignature;
use Symfony\Component\HttpFoundation\Response;

function sendgridRequest(?string $authorization): Request
{
    $request = Request::create('/help-desk/webhooks/sendgrid', 'POST');

    if ($authorization !== null) {
        $request->headers->set('Authorization', $authorization);
    }

    return $request;
}

beforeEach(function () {
    config()->set('help-desk.email.inbound.sendgrid.webhook_username', 'user');
    config()->set('help-desk.email.inbound.sendgrid.webhook_password', 'pass');

    $this->middleware = new VerifySendGridSignature;
    $this->next = fn (Request $request) => new Response('passed', 200);
});

it('passes a request with valid basic auth credentials', function () {
    $authorization = 'Basic '.base64_encode('user:pass');

    $response = $this->middleware->handle(sendgridRequest($authorization), $this->next);

    expect($response->getStatusCode())->toBe(200);
});

it('rejects a request with invalid credentials', function () {
    $authorization = 'Basic '.base64_encode('user:wrong');

    assertAbortsWith(
        fn () => $this->middleware->handle(sendgridRequest($authorization), $this->next),
        403
    );
});

it('rejects a request without an authorization header', function () {
    assertAbortsWith(
        fn () => $this->middleware->handle(sendgridRequest(null), $this->next),
        401
    );
});

it('fails closed when credentials are not configured', function () {
    config()->set('help-desk.email.inbound.sendgrid.webhook_username', null);
    config()->set('help-desk.email.inbound.sendgrid.webhook_password', null);

    $authorization = 'Basic '.base64_encode('user:pass');

    assertAbortsWith(
        fn () => $this->middleware->handle(sendgridRequest($authorization), $this->next),
        403
    );
});
