<?php

namespace JeffersonGoncalves\HelpDesk\Api;

/**
 * The shape of a signed help desk API request.
 *
 * Both halves — the satellite signing and the central application verifying —
 * build the canonical string here, so the two can only disagree by changing
 * this file.
 */
final class HelpDeskSignature
{
    public const APP_HEADER = 'X-HelpDesk-App';

    public const TIMESTAMP_HEADER = 'X-HelpDesk-Timestamp';

    public const NONCE_HEADER = 'X-HelpDesk-Nonce';

    public const SIGNATURE_HEADER = 'X-HelpDesk-Signature';

    public const ALGORITHM = 'sha256';

    /**
     * A nonce is 128 bits as hex. Bounding it keeps a caller from filling the
     * nonce cache with arbitrarily long keys.
     */
    public const NONCE_LENGTH = 32;

    /**
     * The string both sides run through HMAC.
     *
     * The method and the path are in it because signing the body alone lets a
     * captured request be replayed against a different endpoint — a signed
     * "add comment" body would verify just as well against a delete route.
     * The body appears as its hash rather than inline, so the string stays a
     * fixed size whatever is being sent.
     */
    public static function canonical(string $method, string $requestUri, int $timestamp, string $nonce, string $body): string
    {
        return implode("\n", [
            strtoupper($method),
            $requestUri,
            (string) $timestamp,
            $nonce,
            hash(self::ALGORITHM, $body),
        ]);
    }

    /**
     * The header value for a canonical string and a secret.
     */
    public static function sign(string $canonical, string $secret): string
    {
        return self::ALGORITHM.'='.hash_hmac(self::ALGORITHM, $canonical, $secret);
    }

    /**
     * Path plus query, the way the server will see it, derived from whatever
     * the client was given. Taking it from the URL rather than asking the
     * caller to state it removes the most obvious way for the two to differ.
     */
    public static function requestUriFrom(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $query = parse_url($url, PHP_URL_QUERY);

        $path = is_string($path) && $path !== '' ? $path : '/';

        return is_string($query) && $query !== '' ? $path.'?'.$query : $path;
    }

    public static function nonce(): string
    {
        return bin2hex(random_bytes(self::NONCE_LENGTH / 2));
    }
}
