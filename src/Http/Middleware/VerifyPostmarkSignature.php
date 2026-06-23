<?php

namespace JeffersonGoncalves\HelpDesk\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use JeffersonGoncalves\WebhookSignatures\Verifiers\PostmarkSignatureVerifier;
use Symfony\Component\HttpFoundation\Response;

class VerifyPostmarkSignature
{
    /**
     * Verify the Postmark inbound webhook request.
     *
     * Postmark does not sign inbound webhook payloads. Authentication
     * is done via HTTP Basic Auth credentials embedded in the webhook URL.
     * The constant-time credential comparison is delegated to the
     * jeffersongoncalves/laravel-webhook-signatures package verifier.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $username = config('help-desk.email.inbound.postmark.webhook_username');
        $password = config('help-desk.email.inbound.postmark.webhook_password');

        if (empty($username) || empty($password)) {
            Log::warning('Help Desk: Postmark webhook credentials are not configured. Rejecting request (fail closed). Set HELPDESK_POSTMARK_WEBHOOK_USERNAME and HELPDESK_POSTMARK_WEBHOOK_PASSWORD to enable the webhook.');

            abort(403, __('help-desk::emails.errors.invalid_signature'));
        }

        if (! $request->getUser() || ! $request->getPassword()) {
            abort(401, __('help-desk::emails.errors.invalid_signature'));
        }

        $secret = (string) $username.':'.(string) $password;

        if (! (new PostmarkSignatureVerifier)->verify($request, $secret)) {
            abort(403, __('help-desk::emails.errors.invalid_signature'));
        }

        return $next($request);
    }
}
