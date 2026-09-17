<?php

namespace JeffersonGoncalves\HelpDesk\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use JeffersonGoncalves\HelpDesk\Api\HelpDeskSignature;
use JeffersonGoncalves\HelpDesk\Api\HelpDeskSignatureVerifier;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates a satellite application against the central one.
 *
 * The signature proves which application is calling, nothing about which user
 * — the user is asserted in the payload, and the application is trusted to
 * assert only its own. That is why the resolved app key is put on the request
 * for controllers to scope by, and why a key in the body must be ignored.
 */
class VerifyHelpDeskSignature
{
    /**
     * Where the verified app key is left for controllers.
     */
    public const ATTRIBUTE = 'help-desk.app_key';

    public function handle(Request $request, Closure $next): Response
    {
        $appKey = $request->header(HelpDeskSignature::APP_HEADER);

        if (! is_string($appKey) || $appKey === '') {
            return $this->reject();
        }

        $secrets = $this->secretsFor($appKey);

        if ($secrets === []) {
            return $this->reject();
        }

        if (! $this->signatureMatchesAny($request, $secrets)) {
            return $this->reject();
        }

        if (! $this->claimNonce($appKey, (string) $request->header(HelpDeskSignature::NONCE_HEADER))) {
            return $this->reject();
        }

        $request->attributes->set(self::ATTRIBUTE, $appKey);

        return $next($request);
    }

    /**
     * Every configured secret is tried, and none of them short-circuits the
     * loop, so a rejected request takes the same time whichever secret it
     * failed against.
     *
     * @param  list<string>  $secrets
     */
    protected function signatureMatchesAny(Request $request, array $secrets): bool
    {
        $verifier = new HelpDeskSignatureVerifier($this->tolerance());
        $matched = false;

        foreach ($secrets as $secret) {
            $matched = $verifier->verify($request, $secret) || $matched;
        }

        return $matched;
    }

    /**
     * A timestamp window alone leaves every request inside it replayable, so
     * the nonce has to be consumed as well. Cache::add is put-if-absent and
     * atomic, which is what makes two racing replays resolve to one winner.
     *
     * The entry outlives the tolerance by a minute so that a nonce cannot be
     * freed while its timestamp is still considered fresh.
     */
    protected function claimNonce(string $appKey, string $nonce): bool
    {
        return Cache::add("help-desk:nonce:{$appKey}:{$nonce}", true, $this->tolerance() + 60);
    }

    /**
     * @return list<string>
     */
    protected function secretsFor(string $appKey): array
    {
        $secrets = config("help-desk.api.clients.{$appKey}.secrets", []);

        if (! is_array($secrets)) {
            return [];
        }

        // An entry left empty by an unset environment variable must not become
        // a secret that matches something.
        return array_values(array_filter(
            $secrets,
            fn ($secret) => is_string($secret) && $secret !== '',
        ));
    }

    protected function tolerance(): int
    {
        $tolerance = config('help-desk.api.tolerance', 300);

        return is_numeric($tolerance) ? (int) $tolerance : 300;
    }

    /**
     * One response for every failure. Telling a caller whether the application
     * was unknown, the timestamp stale or the signature wrong is free
     * reconnaissance.
     */
    protected function reject(): Response
    {
        return response()->json(['message' => 'Invalid signature.'], 401);
    }
}
