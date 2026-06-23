<?php

use Illuminate\Http\Request;
use JeffersonGoncalves\HelpDesk\Http\Middleware\VerifyPostmarkSignature;
use Symfony\Component\HttpFoundation\Response;

function postmarkRequest(?string $user, ?string $pass): Request
{
    $server = [];

    if ($user !== null) {
        $server['PHP_AUTH_USER'] = $user;
    }

    if ($pass !== null) {
        $server['PHP_AUTH_PW'] = $pass;
    }

    return Request::create('/help-desk/webhooks/postmark', 'POST', [], [], [], $server);
}

beforeEach(function () {
    config()->set('help-desk.email.inbound.postmark.webhook_username', 'user');
    config()->set('help-desk.email.inbound.postmark.webhook_password', 'pass');

    $this->middleware = new VerifyPostmarkSignature;
    $this->next = fn (Request $request) => new Response('passed', 200);
});

it('passes a request with valid basic auth credentials', function () {
    $response = $this->middleware->handle(postmarkRequest('user', 'pass'), $this->next);

    expect($response->getStatusCode())->toBe(200);
});

it('rejects a request with invalid credentials', function () {
    assertAbortsWith(
        fn () => $this->middleware->handle(postmarkRequest('user', 'wrong'), $this->next),
        403
    );
});

it('rejects a request without credentials', function () {
    assertAbortsWith(
        fn () => $this->middleware->handle(postmarkRequest(null, null), $this->next),
        401
    );
});

it('fails closed when credentials are not configured', function () {
    config()->set('help-desk.email.inbound.postmark.webhook_username', null);
    config()->set('help-desk.email.inbound.postmark.webhook_password', null);

    assertAbortsWith(
        fn () => $this->middleware->handle(postmarkRequest('user', 'pass'), $this->next),
        403
    );
});
