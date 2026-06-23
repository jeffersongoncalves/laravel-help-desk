<?php

namespace JeffersonGoncalves\HelpDesk\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use JeffersonGoncalves\WebhookSignatures\Verifiers\MailgunSignatureVerifier;
use Symfony\Component\HttpFoundation\Response;

class VerifyMailgunSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $signingKey = config('help-desk.email.inbound.mailgun.signing_key');

        if (empty($signingKey)) {
            Log::warning('Help Desk: Mailgun webhook signing key is not configured. Rejecting request (fail closed). Set HELPDESK_MAILGUN_SIGNING_KEY to enable the webhook.');

            abort(403, __('help-desk::emails.errors.invalid_signature'));
        }

        if (! (new MailgunSignatureVerifier)->verify($request, (string) $signingKey)) {
            abort(403, __('help-desk::emails.errors.invalid_signature'));
        }

        return $next($request);
    }
}
