<?php

namespace JeffersonGoncalves\HelpDesk\Mail\Drivers;

use JeffersonGoncalves\HelpDesk\Contracts\EmailDriver;
use JeffersonGoncalves\HelpDesk\Exceptions\EmailProcessingException;
use JeffersonGoncalves\HelpDesk\Models\EmailChannel;

class ImapDriver implements EmailDriver
{
    public function poll(EmailChannel $channel): array
    {
        $this->ensureDependenciesInstalled();

        $settings = $channel->settings;
        $emails = [];

        try {
            $client = new \Webklex\PHPIMAP\ClientManager;
            $connection = $client->make([
                'host' => $settings['host'],
                'port' => $settings['port'] ?? 993,
                'encryption' => $settings['encryption'] ?? 'ssl',
                'validate_cert' => $settings['validate_cert'] ?? true,
                'username' => $settings['username'],
                'password' => $settings['password'],
                'protocol' => 'imap',
            ]);

            $connection->connect();

            $folder = $connection->getFolder($settings['folder'] ?? 'INBOX');
            $messages = $folder->messages()->unseen()->get();

            foreach ($messages as $message) {
                $emails[] = [
                    'message_id' => $message->getMessageId()?->toString() ?? '<'.uniqid().'@imap>',
                    'in_reply_to' => $message->getInReplyTo()?->toString(),
                    'references' => $message->getReferences()?->toString(),
                    'from' => $message->getFrom()[0]->mail ?? '',
                    'from_name' => $message->getFrom()[0]->personal ?? null,
                    'to_addresses' => collect($message->getTo())->pluck('mail')->toArray(),
                    'cc_addresses' => collect($message->getCc())->pluck('mail')->toArray(),
                    'subject' => $message->getSubject()?->toString(),
                    'text_body' => $message->getTextBody(),
                    'html_body' => $message->getHTMLBody(),
                    'attachments' => $this->extractAttachments($message),
                ];

                if ($settings['mark_as_read'] ?? true) {
                    $message->setFlag('Seen');
                }

                if (! empty($settings['move_processed_to'])) {
                    $message->move($settings['move_processed_to']);
                }
            }

            $channel->markPolled();
        } catch (\Webklex\PHPIMAP\Exceptions\ConnectionFailedException $e) {
            $channel->markError($e->getMessage());
            throw EmailProcessingException::connectionFailed($e->getMessage());
        }

        return $emails;
    }

    public function getDriverName(): string
    {
        return 'imap';
    }

    protected function ensureDependenciesInstalled(): void
    {
        if (! class_exists(\Webklex\PHPIMAP\ClientManager::class)) {
            throw EmailProcessingException::driverNotInstalled('IMAP', 'webklex/php-imap');
        }
    }

    protected function extractAttachments($message): array
    {
        $attachments = [];

        foreach ($message->getAttachments() as $attachment) {
            $attachments[] = [
                'filename' => $attachment->getName(),
                'content' => $attachment->getContent(),
                'mime_type' => $attachment->getMimeType(),
                'size' => $attachment->getSize(),
            ];
        }

        return $attachments;
    }
}
