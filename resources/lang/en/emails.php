<?php

return [
    'inbound' => [
        'processed' => 'Inbound email processed successfully.',
        'failed' => 'Failed to process inbound email.',
        'ignored' => 'Inbound email ignored.',
        'duplicate' => 'Duplicate email detected, skipping.',
        'no_sender' => 'Could not determine sender email address.',
        'no_user' => 'No user found for email address :email.',
        'ticket_created' => 'New ticket created from email.',
        'comment_added' => 'Comment added to ticket from email.',
    ],

    'threading' => [
        'matched_by_header' => 'Matched to ticket via email header.',
        'matched_by_reference' => 'Matched to ticket via subject reference.',
        'no_match' => 'No existing ticket found, creating new ticket.',
    ],

    'channels' => [
        'imap' => 'IMAP',
        'mailgun' => 'Mailgun',
        'sendgrid' => 'SendGrid',
    ],

    'errors' => [
        'driver_not_installed' => 'The :driver driver requires the :package package. Please install it via: composer require :package',
        'connection_failed' => 'Failed to connect to mail server: :error',
        'invalid_signature' => 'Invalid webhook signature.',
        'channel_not_found' => 'Email channel not found.',
        'channel_inactive' => 'Email channel is not active.',
        'processing_error' => 'Error processing email: :error',
    ],
];
