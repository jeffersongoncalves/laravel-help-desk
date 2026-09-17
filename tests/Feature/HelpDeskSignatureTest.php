<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use JeffersonGoncalves\HelpDesk\Api\HelpDeskSignature;
use JeffersonGoncalves\HelpDesk\Api\HelpDeskSigner;
use JeffersonGoncalves\HelpDesk\Http\Middleware\VerifyHelpDeskSignature;

const SECRET = 'the-current-secret';

const PREVIOUS = 'the-previous-secret';

beforeEach(function () {
    config()->set('help-desk.api.tolerance', 300);
    config()->set('help-desk.api.clients', [
        'app-a' => ['secrets' => [SECRET, PREVIOUS]],
    ]);

    Cache::flush();

    Route::middleware(VerifyHelpDeskSignature::class)->group(function () {
        Route::post('signed/echo', fn () => response()->json([
            'app_key' => request()->attributes->get(VerifyHelpDeskSignature::ATTRIBUTE),
        ]));

        Route::post('signed/other', fn () => response()->json(['ok' => true]));
        Route::get('signed/echo', fn () => response()->json(['ok' => true]));
    });
});

/**
 * Signs for real, so the tests exercise the signer and the verifier against
 * each other rather than a hand-rolled header the production client never
 * sends.
 *
 * @return array<string, string>
 */
function sign(string $method, string $url, string $body, string $secret = SECRET, string $appKey = 'app-a'): array
{
    return (new HelpDeskSigner)->headersFor($method, $url, $body, $appKey, $secret);
}

function postSigned(array $headers, string $url = '/signed/echo', string $body = '{"hello":"world"}')
{
    return test()->call('POST', $url, [], [], [], transformHeaders($headers), $body);
}

/**
 * Laravel's `call()` wants HTTP_* server keys.
 *
 * @return array<string, string>
 */
function transformHeaders(array $headers): array
{
    $server = [];

    foreach ($headers as $name => $value) {
        $server['HTTP_'.str_replace('-', '_', strtoupper($name))] = $value;
    }

    return $server;
}

it('accepts a correctly signed request and exposes the app key', function () {
    $body = '{"hello":"world"}';

    postSigned(sign('POST', '/signed/echo', $body), '/signed/echo', $body)
        ->assertOk()
        ->assertJson(['app_key' => 'app-a']);
});

it('accepts a request signed with the previous secret', function () {
    $body = '{"hello":"world"}';

    postSigned(sign('POST', '/signed/echo', $body, PREVIOUS), '/signed/echo', $body)
        ->assertOk();
});

it('rejects a tampered body', function () {
    $headers = sign('POST', '/signed/echo', '{"hello":"world"}');

    postSigned($headers, '/signed/echo', '{"hello":"tampered"}')->assertUnauthorized();
});

it('rejects a signature replayed against another path', function () {
    $body = '{"hello":"world"}';
    $headers = sign('POST', '/signed/echo', $body);

    postSigned($headers, '/signed/other', $body)->assertUnauthorized();
});

it('rejects a signature replayed against another method', function () {
    $headers = sign('POST', '/signed/echo', '');

    test()->call('GET', '/signed/echo', [], [], [], transformHeaders($headers))
        ->assertUnauthorized();
});

it('rejects a stale timestamp', function () {
    $body = '{"hello":"world"}';
    $headers = sign('POST', '/signed/echo', $body);
    $headers[HelpDeskSignature::TIMESTAMP_HEADER] = (string) (time() - 3600);

    postSigned($headers, '/signed/echo', $body)->assertUnauthorized();
});

it('rejects a timestamp from the future', function () {
    $body = '{"hello":"world"}';
    $headers = sign('POST', '/signed/echo', $body);
    $headers[HelpDeskSignature::TIMESTAMP_HEADER] = (string) (time() + 3600);

    postSigned($headers, '/signed/echo', $body)->assertUnauthorized();
});

it('rejects a non-numeric timestamp', function () {
    $body = '{"hello":"world"}';
    $headers = sign('POST', '/signed/echo', $body);
    $headers[HelpDeskSignature::TIMESTAMP_HEADER] = 'yesterday';

    postSigned($headers, '/signed/echo', $body)->assertUnauthorized();
});

it('rejects a replayed nonce', function () {
    $body = '{"hello":"world"}';
    $headers = sign('POST', '/signed/echo', $body);

    postSigned($headers, '/signed/echo', $body)->assertOk();
    postSigned($headers, '/signed/echo', $body)->assertUnauthorized();
});

it('rejects a malformed nonce', function () {
    $body = '{"hello":"world"}';
    $headers = sign('POST', '/signed/echo', $body);
    $headers[HelpDeskSignature::NONCE_HEADER] = 'not-hex';

    postSigned($headers, '/signed/echo', $body)->assertUnauthorized();
});

it('rejects an unknown application', function () {
    $body = '{"hello":"world"}';

    postSigned(sign('POST', '/signed/echo', $body, SECRET, 'app-z'), '/signed/echo', $body)
        ->assertUnauthorized();
});

it('rejects a wrong secret', function () {
    $body = '{"hello":"world"}';

    postSigned(sign('POST', '/signed/echo', $body, 'not-the-secret'), '/signed/echo', $body)
        ->assertUnauthorized();
});

it('fails closed when the application has no usable secret', function () {
    // An unset environment variable leaves a null in the list. It must not
    // become a secret that matches anything.
    config()->set('help-desk.api.clients', ['app-a' => ['secrets' => [null, '']]]);

    $body = '{"hello":"world"}';

    postSigned(sign('POST', '/signed/echo', $body, ''), '/signed/echo', $body)
        ->assertUnauthorized();
});

it('rejects a request with no signature headers at all', function () {
    postSigned([], '/signed/echo', '{"hello":"world"}')->assertUnauthorized();
});

it('signs the query string, not only the path', function () {
    $body = '';
    $headers = sign('POST', '/signed/echo?status=open', $body);

    // Same signature, different filter: the server must not accept it.
    postSigned($headers, '/signed/echo?status=closed', $body)->assertUnauthorized();
});

it('gives the same failure for every reason', function () {
    $body = '{"hello":"world"}';

    $unknownApp = postSigned(sign('POST', '/signed/echo', $body, SECRET, 'app-z'), '/signed/echo', $body);
    $wrongSecret = postSigned(sign('POST', '/signed/echo', $body, 'nope'), '/signed/echo', $body);

    expect($unknownApp->json())->toBe($wrongSecret->json())
        ->and($unknownApp->status())->toBe($wrongSecret->status());
});

it('derives the request uri the server will see from a full url', function () {
    expect(HelpDeskSignature::requestUriFrom('https://support.example.com/help-desk/api/tickets'))
        ->toBe('/help-desk/api/tickets')
        ->and(HelpDeskSignature::requestUriFrom('https://support.example.com/help-desk/api/tickets?status=open'))
        ->toBe('/help-desk/api/tickets?status=open')
        ->and(HelpDeskSignature::requestUriFrom('https://support.example.com'))
        ->toBe('/');
});

it('produces a fresh nonce every time', function () {
    $nonces = array_map(fn () => HelpDeskSignature::nonce(), range(1, 50));

    expect(array_unique($nonces))->toHaveCount(50)
        ->and($nonces[0])->toHaveLength(HelpDeskSignature::NONCE_LENGTH);
});
