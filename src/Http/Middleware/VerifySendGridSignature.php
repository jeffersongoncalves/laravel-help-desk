<?php

namespace JeffersonGoncalves\HelpDesk\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifySendGridSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $username = config('help-desk.email.inbound.sendgrid.webhook_username');
        $password = config('help-desk.email.inbound.sendgrid.webhook_password');

        if (empty($username) || empty($password)) {
            Log::warning('Help Desk: SendGrid webhook credentials are not configured. Rejecting request (fail closed). Set HELPDESK_SENDGRID_WEBHOOK_USERNAME and HELPDESK_SENDGRID_WEBHOOK_PASSWORD to enable the webhook.');

            abort(403, __('help-desk::emails.errors.invalid_signature'));
        }

        $authHeader = $request->header('Authorization');

        if (! $authHeader || ! str_starts_with($authHeader, 'Basic ')) {
            abort(401, __('help-desk::emails.errors.invalid_signature'));
        }

        $credentials = base64_decode(substr($authHeader, 6));
        [$providedUsername, $providedPassword] = explode(':', $credentials, 2) + [1 => ''];

        if (! hash_equals($username, $providedUsername) || ! hash_equals($password, $providedPassword)) {
            abort(403, __('help-desk::emails.errors.invalid_signature'));
        }

        return $next($request);
    }
}
