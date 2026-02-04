<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | Define the models used by the help desk system. The user model is the
    | model that creates tickets, while the operator model is the model
    | that handles and manages tickets.
    |
    */

    'models' => [
        'user' => \App\Models\User::class,
        'operator' => \App\Models\User::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Ticket Settings
    |--------------------------------------------------------------------------
    */

    'ticket' => [
        'reference_prefix' => 'HD',
        'default_status' => 'open',
        'default_priority' => 'medium',
        'allowed_extensions' => [
            'jpg', 'jpeg', 'png', 'gif', 'pdf',
            'doc', 'docx', 'xls', 'xlsx', 'csv',
            'txt', 'zip',
        ],
        'max_file_size' => 10240, // KB
        'max_attachments_per_comment' => 5,
        'attachment_disk' => env('HELPDESK_ATTACHMENT_DISK', 'local'),
        'attachment_path' => 'help-desk/attachments',
        'auto_close_days' => null,
        'allow_reopen' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Settings
    |--------------------------------------------------------------------------
    */

    'email' => [
        'enabled' => env('HELPDESK_EMAIL_ENABLED', true),

        'from' => [
            'address' => env('HELPDESK_FROM_ADDRESS', env('MAIL_FROM_ADDRESS')),
            'name' => env('HELPDESK_FROM_NAME', env('MAIL_FROM_NAME')),
        ],

        'subject_prefix' => '[Help Desk #:reference]',
        'threading_enabled' => true,

        'inbound' => [
            'driver' => env('HELPDESK_INBOUND_DRIVER', null),

            'imap' => [
                'host' => env('HELPDESK_IMAP_HOST'),
                'port' => env('HELPDESK_IMAP_PORT', 993),
                'encryption' => env('HELPDESK_IMAP_ENCRYPTION', 'ssl'),
                'validate_cert' => env('HELPDESK_IMAP_VALIDATE_CERT', true),
                'username' => env('HELPDESK_IMAP_USERNAME'),
                'password' => env('HELPDESK_IMAP_PASSWORD'),
                'folder' => env('HELPDESK_IMAP_FOLDER', 'INBOX'),
                'mark_as_read' => true,
                'move_processed_to' => null,
                'poll_interval' => 5,
            ],

            'mailgun' => [
                'signing_key' => env('HELPDESK_MAILGUN_SIGNING_KEY'),
            ],

            'sendgrid' => [
                'webhook_username' => env('HELPDESK_SENDGRID_WEBHOOK_USERNAME'),
                'webhook_password' => env('HELPDESK_SENDGRID_WEBHOOK_PASSWORD'),
            ],

            'resend' => [
                'api_key' => env('HELPDESK_RESEND_API_KEY', env('RESEND_API_KEY')),
                'webhook_secret' => env('HELPDESK_RESEND_WEBHOOK_SECRET'),
            ],

            'postmark' => [
                'webhook_username' => env('HELPDESK_POSTMARK_WEBHOOK_USERNAME'),
                'webhook_password' => env('HELPDESK_POSTMARK_WEBHOOK_PASSWORD'),
            ],

            'store_raw_payload' => env('HELPDESK_STORE_RAW_PAYLOAD', false),
            'retention_days' => 30,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Settings
    |--------------------------------------------------------------------------
    */

    'webhooks' => [
        'prefix' => 'help-desk/webhooks',
        'middleware' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Settings
    |--------------------------------------------------------------------------
    */

    'notifications' => [
        'channels' => ['mail'],

        'notify_on' => [
            'ticket_created' => true,
            'ticket_assigned' => true,
            'ticket_status_changed' => true,
            'comment_added' => true,
            'ticket_closed' => true,
        ],

        'queue' => env('HELPDESK_NOTIFICATION_QUEUE', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Register Default Listeners
    |--------------------------------------------------------------------------
    |
    | When enabled, the package will automatically register its default
    | event listeners for notifications and history logging.
    |
    */

    'register_default_listeners' => true,

];
