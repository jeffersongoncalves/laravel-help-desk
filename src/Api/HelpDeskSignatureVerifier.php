<?php

namespace JeffersonGoncalves\HelpDesk\Api;

use Illuminate\Http\Request;
use JeffersonGoncalves\WebhookSignatures\Contracts\SignatureVerifier;

/**
 * The server half, against a single secret.
 *
 * Deliberately pure and single-secret, to match the `SignatureVerifier`
 * contract it implements: looking the application up, walking its list of
 * secrets, and consuming the nonce all live in VerifyHelpDeskSignature. A
 * verifier that consumed a nonce could not be called twice for two secrets.
 *
 * Fails closed on every missing or malformed input, as the contract requires.
 */
class HelpDeskSignatureVerifier implements SignatureVerifier
{
    public function __construct(protected int $tolerance = 300) {}

    public function verify(Request $request, string $secret): bool
    {
        if ($secret === '') {
            return false;
        }

        $timestamp = $request->header(HelpDeskSignature::TIMESTAMP_HEADER);
        $nonce = $request->header(HelpDeskSignature::NONCE_HEADER);
        $signature = $request->header(HelpDeskSignature::SIGNATURE_HEADER);

        if (! is_string($timestamp) || ! is_string($nonce) || ! is_string($signature)) {
            return false;
        }

        if (! $this->timestampIsFresh($timestamp)) {
            return false;
        }

        if (! $this->nonceIsWellFormed($nonce)) {
            return false;
        }

        $canonical = HelpDeskSignature::canonical(
            $request->getMethod(),
            $request->getRequestUri(),
            (int) $timestamp,
            $nonce,
            $request->getContent(),
        );

        return hash_equals(HelpDeskSignature::sign($canonical, $secret), $signature);
    }

    /**
     * Rejects both a stale timestamp and one from the future — a clock the
     * caller controls is not a reason to widen the window in one direction.
     */
    protected function timestampIsFresh(string $timestamp): bool
    {
        if (! ctype_digit($timestamp)) {
            return false;
        }

        return abs(time() - (int) $timestamp) <= $this->tolerance;
    }

    protected function nonceIsWellFormed(string $nonce): bool
    {
        return strlen($nonce) === HelpDeskSignature::NONCE_LENGTH && ctype_xdigit($nonce);
    }
}
