<?php

namespace JeffersonGoncalves\HelpDesk\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use JeffersonGoncalves\HelpDesk\Mail\Drivers\MailgunDriver;
use JeffersonGoncalves\HelpDesk\Mail\EmailParser;
use JeffersonGoncalves\HelpDesk\Models\EmailChannel;
use JeffersonGoncalves\HelpDesk\Services\InboundEmailService;

class MailgunWebhookController extends Controller
{
    public function __construct(
        protected InboundEmailService $inboundEmailService,
        protected EmailParser $emailParser,
        protected MailgunDriver $mailgunDriver,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->all();
        $parsed = $this->mailgunDriver->parseWebhookPayload($payload);
        $parsedEmail = $this->emailParser->parse($parsed);

        $recipient = $parsed['to_addresses'][0] ?? null;
        $channel = $recipient
            ? EmailChannel::where('email_address', $recipient)->where('driver', 'mailgun')->first()
            : null;

        $this->inboundEmailService->store([
            'email_channel_id' => $channel?->id,
            'message_id' => $parsedEmail['message_id'],
            'in_reply_to' => $parsedEmail['in_reply_to'],
            'references' => $parsedEmail['references'],
            'from_address' => $parsedEmail['from_address'],
            'from_name' => $parsedEmail['from_name'],
            'to_addresses' => $parsedEmail['to_addresses'],
            'cc_addresses' => $parsedEmail['cc_addresses'],
            'subject' => $parsedEmail['subject'],
            'text_body' => $parsedEmail['text_body'],
            'html_body' => $parsedEmail['html_body'],
            'raw_payload' => config('help-desk.email.inbound.store_raw_payload') ? json_encode($payload) : null,
            'status' => 'pending',
        ]);

        return response()->json(['status' => 'ok']);
    }
}
