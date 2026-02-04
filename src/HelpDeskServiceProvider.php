<?php

namespace JeffersonGoncalves\HelpDesk;

use Illuminate\Support\Facades\Event;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use JeffersonGoncalves\HelpDesk\Commands\CleanInboundEmailsCommand;
use JeffersonGoncalves\HelpDesk\Commands\CloseStaleTicketsCommand;
use JeffersonGoncalves\HelpDesk\Commands\PollImapMailboxCommand;
use JeffersonGoncalves\HelpDesk\Events\CommentAdded;
use JeffersonGoncalves\HelpDesk\Events\InboundEmailReceived;
use JeffersonGoncalves\HelpDesk\Events\TicketAssigned;
use JeffersonGoncalves\HelpDesk\Events\TicketClosed;
use JeffersonGoncalves\HelpDesk\Events\TicketCreated;
use JeffersonGoncalves\HelpDesk\Events\TicketStatusChanged;
use JeffersonGoncalves\HelpDesk\Listeners\LogTicketHistory;
use JeffersonGoncalves\HelpDesk\Listeners\ProcessInboundEmail;
use JeffersonGoncalves\HelpDesk\Listeners\SendCommentAddedNotification;
use JeffersonGoncalves\HelpDesk\Listeners\SendTicketAssignedNotification;
use JeffersonGoncalves\HelpDesk\Listeners\SendTicketCreatedNotification;
use JeffersonGoncalves\HelpDesk\Listeners\SendTicketStatusChangedNotification;
use JeffersonGoncalves\HelpDesk\Services\AttachmentService;
use JeffersonGoncalves\HelpDesk\Services\CommentService;
use JeffersonGoncalves\HelpDesk\Services\DepartmentService;
use JeffersonGoncalves\HelpDesk\Services\InboundEmailService;
use JeffersonGoncalves\HelpDesk\Services\TicketService;

class HelpDeskServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('help-desk')
            ->hasConfigFile()
            ->hasMigrations([
                'create_help_desk_departments_table',
                'create_help_desk_categories_table',
                'create_help_desk_tickets_table',
                'create_help_desk_ticket_comments_table',
                'create_help_desk_ticket_attachments_table',
                'create_help_desk_ticket_history_table',
                'create_help_desk_department_operator_table',
                'create_help_desk_ticket_watchers_table',
                'create_help_desk_canned_responses_table',
                'create_help_desk_email_channels_table',
                'create_help_desk_inbound_emails_table',
            ])
            ->hasTranslations()
            ->hasRoute('webhooks')
            ->hasCommands([
                PollImapMailboxCommand::class,
                CleanInboundEmailsCommand::class,
                CloseStaleTicketsCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(TicketService::class);
        $this->app->singleton(CommentService::class);
        $this->app->singleton(DepartmentService::class);
        $this->app->singleton(AttachmentService::class);
        $this->app->singleton(InboundEmailService::class);

        $this->app->singleton(HelpDeskManager::class, function ($app) {
            return new HelpDeskManager(
                $app->make(TicketService::class),
                $app->make(CommentService::class),
                $app->make(DepartmentService::class),
                $app->make(AttachmentService::class),
            );
        });
    }

    public function packageBooted(): void
    {
        if (config('help-desk.register_default_listeners', true)) {
            $this->registerEventListeners();
        }
    }

    protected function registerEventListeners(): void
    {
        // History logging (subscriber)
        Event::subscribe(LogTicketHistory::class);

        // Notification listeners
        Event::listen(TicketCreated::class, SendTicketCreatedNotification::class);
        Event::listen(TicketStatusChanged::class, SendTicketStatusChangedNotification::class);
        Event::listen(CommentAdded::class, SendCommentAddedNotification::class);
        Event::listen(TicketAssigned::class, SendTicketAssignedNotification::class);

        // Inbound email processing
        Event::listen(InboundEmailReceived::class, ProcessInboundEmail::class);
    }
}
