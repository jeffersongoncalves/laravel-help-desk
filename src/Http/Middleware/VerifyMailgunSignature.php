<?php

namespace JeffersonGoncalves\HelpDesk\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyMailgunSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $signingKey = config('help-desk.email.inbound.mailgun.signing_key');

        if (empty($signingKey)) {
            return $next($request);
        }

        $timestamp = $request->input('timestamp');
        $token = $request->input('token');
        $signature = $request->input('signature');

        if (! $timestamp || ! $token || ! $signature) {
            abort(403, __('help-desk::emails.errors.invalid_signature'));
        }

        $expectedSignature = hash_hmac('sha256', $timestamp.$token, $signingKey);

        if (! hash_equals($expectedSignature, $signature)) {
            abort(403, __('help-desk::emails.errors.invalid_signature'));
        }

        return $next($request);
    }
}
