<?php

use Illuminate\Support\Facades\Route;
use JeffersonGoncalves\HelpDesk\Http\Controllers\MailgunWebhookController;
use JeffersonGoncalves\HelpDesk\Http\Controllers\ResendWebhookController;
use JeffersonGoncalves\HelpDesk\Http\Controllers\SendGridWebhookController;
use JeffersonGoncalves\HelpDesk\Http\Middleware\VerifyMailgunSignature;
use JeffersonGoncalves\HelpDesk\Http\Middleware\VerifyResendSignature;
use JeffersonGoncalves\HelpDesk\Http\Middleware\VerifySendGridSignature;

Route::prefix(config('help-desk.webhooks.prefix', 'help-desk/webhooks'))
    ->middleware(config('help-desk.webhooks.middleware', []))
    ->group(function () {
        Route::post('mailgun', MailgunWebhookController::class)
            ->middleware(VerifyMailgunSignature::class)
            ->name('help-desk.webhooks.mailgun');

        Route::post('sendgrid', SendGridWebhookController::class)
            ->middleware(VerifySendGridSignature::class)
            ->name('help-desk.webhooks.sendgrid');

        Route::post('resend', ResendWebhookController::class)
            ->middleware(VerifyResendSignature::class)
            ->name('help-desk.webhooks.resend');
    });
