<?php

namespace JeffersonGoncalves\HelpDesk\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use JeffersonGoncalves\WebhookSignatures\Verifiers\ResendSignatureVerifier;
use Symfony\Component\HttpFoundation\Response;

class VerifyResendSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('help-desk.email.inbound.resend.webhook_secret');

        if (empty($secret)) {
            Log::warning('Help Desk: Resend webhook secret is not configured. Rejecting request (fail closed). Set HELPDESK_RESEND_WEBHOOK_SECRET to enable the webhook.');

            abort(403, __('help-desk::emails.errors.invalid_signature'));
        }

        if (! (new ResendSignatureVerifier)->verify($request, (string) $secret)) {
            abort(403, __('help-desk::emails.errors.invalid_signature'));
        }

        return $next($request);
    }
}
