<?php

namespace JeffersonGoncalves\HelpDesk\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyPostmarkSignature
{
    /**
     * Verify the Postmark inbound webhook request.
     *
     * Postmark does not sign inbound webhook payloads. Authentication
     * is done via HTTP Basic Auth credentials or by checking the
     * source IP or a custom token in the webhook URL.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $username = config('help-desk.email.inbound.postmark.webhook_username');
        $password = config('help-desk.email.inbound.postmark.webhook_password');

        if (empty($username) || empty($password)) {
            return $next($request);
        }

        $providedUsername = $request->getUser();
        $providedPassword = $request->getPassword();

        if (! $providedUsername || ! $providedPassword) {
            abort(401, __('help-desk::emails.errors.invalid_signature'));
        }

        if (! hash_equals($username, $providedUsername) || ! hash_equals($password, $providedPassword)) {
            abort(403, __('help-desk::emails.errors.invalid_signature'));
        }

        return $next($request);
    }
}
