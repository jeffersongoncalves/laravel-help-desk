<?php

use Illuminate\Support\Facades\Route;
use JeffersonGoncalves\HelpDesk\Http\Controllers\MailgunWebhookController;
use JeffersonGoncalves\HelpDesk\Http\Controllers\ResendWebhookController;
use JeffersonGoncalves\HelpDesk\Http\Controllers\SendGridWebhookController;
use JeffersonGoncalves\HelpDesk\Http\Middleware\VerifyMailgunSignature;
use JeffersonGoncalves\HelpDesk\Http\Middleware\VerifyResendSignature;
use JeffersonGoncalves\HelpDesk\Http\Middleware\VerifySendGridSignature;

Route::post('mailgun', MailgunWebhookController::class)
    ->middleware(VerifyMailgunSignature::class)
    ->name('help-desk.webhooks.mailgun');

Route::post('sendgrid', SendGridWebhookController::class)
    ->middleware(VerifySendGridSignature::class)
    ->name('help-desk.webhooks.sendgrid');

Route::post('resend', ResendWebhookController::class)
    ->middleware(VerifyResendSignature::class)
    ->name('help-desk.webhooks.resend');
