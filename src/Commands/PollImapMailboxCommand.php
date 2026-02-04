<?php

namespace JeffersonGoncalves\HelpDesk\Commands;

use Illuminate\Console\Command;
use JeffersonGoncalves\HelpDesk\Mail\Drivers\ImapDriver;
use JeffersonGoncalves\HelpDesk\Mail\EmailParser;
use JeffersonGoncalves\HelpDesk\Models\EmailChannel;
use JeffersonGoncalves\HelpDesk\Services\InboundEmailService;

class PollImapMailboxCommand extends Command
{
    protected $signature = 'help-desk:poll-imap
                            {--channel= : Specific email channel ID to poll}';

    protected $description = 'Poll IMAP mailboxes for new inbound emails';

    public function __construct(
        protected InboundEmailService $inboundEmailService,
        protected EmailParser $emailParser,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (config('help-desk.email.inbound.driver') !== 'imap') {
            $this->info('IMAP inbound driver is not active. Skipping.');

            return self::SUCCESS;
        }

        $driver = new ImapDriver;

        $query = EmailChannel::active()->byDriver('imap');

        if ($channelId = $this->option('channel')) {
            $query->where('id', $channelId);
        }

        $channels = $query->get();

        if ($channels->isEmpty()) {
            $this->info('No active IMAP channels found.');

            return self::SUCCESS;
        }

        foreach ($channels as $channel) {
            $this->info("Polling channel: {$channel->name} ({$channel->email_address})");

            try {
                $emails = $driver->poll($channel);

                $count = count($emails);
                $this->info("Found {$count} new email(s).");

                foreach ($emails as $emailData) {
                    $parsed = $this->emailParser->parse($emailData);

                    $this->inboundEmailService->store([
                        'email_channel_id' => $channel->id,
                        'message_id' => $parsed['message_id'],
                        'in_reply_to' => $parsed['in_reply_to'],
                        'references' => $parsed['references'],
                        'from_address' => $parsed['from_address'],
                        'from_name' => $parsed['from_name'],
                        'to_addresses' => $parsed['to_addresses'],
                        'cc_addresses' => $parsed['cc_addresses'],
                        'subject' => $parsed['subject'],
                        'text_body' => $parsed['text_body'],
                        'html_body' => $parsed['html_body'],
                        'raw_payload' => config('help-desk.email.inbound.store_raw_payload')
                            ? json_encode($emailData)
                            : null,
                        'status' => 'pending',
                    ]);
                }

                $this->info('Processed '.count($emails)." email(s) from {$channel->name}.");
            } catch (\Throwable $e) {
                $this->error("Error polling {$channel->name}: {$e->getMessage()}");
                $channel->markError($e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
