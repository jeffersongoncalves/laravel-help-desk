<?php

namespace JeffersonGoncalves\HelpDesk\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyResendSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('help-desk.email.inbound.resend.webhook_secret');

        if (empty($secret)) {
            return $next($request);
        }

        $svixId = $request->header('svix-id');
        $svixTimestamp = $request->header('svix-timestamp');
        $svixSignature = $request->header('svix-signature');

        if (! $svixId || ! $svixTimestamp || ! $svixSignature) {
            abort(403, __('help-desk::emails.errors.invalid_signature'));
        }

        // Verify timestamp is within tolerance (5 minutes)
        $timestamp = (int) $svixTimestamp;
        if (abs(time() - $timestamp) > 300) {
            abort(403, __('help-desk::emails.errors.invalid_signature'));
        }

        $payload = $request->getContent();
        $signedContent = "{$svixId}.{$svixTimestamp}.{$payload}";

        $secretBytes = base64_decode(
            str_starts_with($secret, 'whsec_') ? substr($secret, 6) : $secret
        );

        $expectedSignature = base64_encode(
            hash_hmac('sha256', $signedContent, $secretBytes, true)
        );

        $signatures = explode(' ', $svixSignature);
        $verified = false;

        foreach ($signatures as $sig) {
            $parts = explode(',', $sig, 2);
            $sigValue = $parts[1] ?? $parts[0];

            if (hash_equals($expectedSignature, $sigValue)) {
                $verified = true;
                break;
            }
        }

        if (! $verified) {
            abort(403, __('help-desk::emails.errors.invalid_signature'));
        }

        return $next($request);
    }
}
