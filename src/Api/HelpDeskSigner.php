<?php

namespace JeffersonGoncalves\HelpDesk\Api;

/**
 * The client half: produces the headers a satellite sends.
 *
 * `laravel-webhook-signatures` only verifies, so this side has to exist here.
 */
class HelpDeskSigner
{
    /**
     * @return array<string, string>
     */
    public function headersFor(string $method, string $url, string $body, string $appKey, string $secret): array
    {
        $timestamp = time();
        $nonce = HelpDeskSignature::nonce();

        $canonical = HelpDeskSignature::canonical(
            $method,
            HelpDeskSignature::requestUriFrom($url),
            $timestamp,
            $nonce,
            $body,
        );

        return [
            HelpDeskSignature::APP_HEADER => $appKey,
            HelpDeskSignature::TIMESTAMP_HEADER => (string) $timestamp,
            HelpDeskSignature::NONCE_HEADER => $nonce,
            HelpDeskSignature::SIGNATURE_HEADER => HelpDeskSignature::sign($canonical, $secret),
        ];
    }
}
