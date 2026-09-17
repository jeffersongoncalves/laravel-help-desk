<?php

namespace JeffersonGoncalves\HelpDesk;

use Illuminate\Support\Facades\Event;
use JeffersonGoncalves\HelpDesk\Api\Repositories\ApiAttachmentRepository;
use JeffersonGoncalves\HelpDesk\Api\Repositories\ApiCommentRepository;
use JeffersonGoncalves\HelpDesk\Api\Repositories\ApiDepartmentRepository;
use JeffersonGoncalves\HelpDesk\Api\Repositories\ApiTicketRepository;
use JeffersonGoncalves\HelpDesk\Commands\CleanInboundEmailsCommand;
use JeffersonGoncalves\HelpDesk\Commands\CloseStaleTicketsCommand;
use JeffersonGoncalves\HelpDesk\Commands\PollImapMailboxCommand;
use JeffersonGoncalves\HelpDesk\Contracts\AttachmentRepository;
use JeffersonGoncalves\HelpDesk\Contracts\CommentRepository;
use JeffersonGoncalves\HelpDesk\Contracts\DepartmentRepository;
use JeffersonGoncalves\HelpDesk\Contracts\TicketRepository;
use JeffersonGoncalves\HelpDesk\Events\CommentAdded;
use JeffersonGoncalves\HelpDesk\Events\InboundEmailReceived;
use JeffersonGoncalves\HelpDesk\Events\TicketAssigned;
use JeffersonGoncalves\HelpDesk\Events\TicketCreated;
use JeffersonGoncalves\HelpDesk\Events\TicketStatusChanged;
use JeffersonGoncalves\HelpDesk\Exceptions\UnsupportedDriverException;
use JeffersonGoncalves\HelpDesk\Listeners\ApplySlaPolicy;
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
use JeffersonGoncalves\HelpDesk\Services\SlaService;
use JeffersonGoncalves\HelpDesk\Services\TicketService;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

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
                'add_app_key_to_help_desk_tickets_table',
                'add_metadata_to_help_desk_ticket_watchers_table',
                'create_help_desk_sla_policies_table',
                'add_sla_to_help_desk_tickets_table',
            ])
            ->hasTranslations()
            ->hasRoute('webhooks')
            ->hasCommands([
                PollImapMailboxCommand::class,
                CleanInboundEmailsCommand::class,
                CloseStaleTicketsCommand::class,
            ]);
    }

    /**
     * The implementation of each repository contract, per driver.
     *
     * @var array<string, array<class-string, class-string>>
     */
    protected const DRIVERS = [
        'database' => [
            TicketRepository::class => TicketService::class,
            CommentRepository::class => CommentService::class,
            DepartmentRepository::class => DepartmentService::class,
            AttachmentRepository::class => AttachmentService::class,
        ],
        'api' => [
            TicketRepository::class => ApiTicketRepository::class,
            CommentRepository::class => ApiCommentRepository::class,
            DepartmentRepository::class => ApiDepartmentRepository::class,
            AttachmentRepository::class => ApiAttachmentRepository::class,
        ],
    ];

    public function packageRegistered(): void
    {
        $this->app->singleton(TicketService::class);
        $this->app->singleton(CommentService::class);
        $this->app->singleton(DepartmentService::class);
        $this->app->singleton(AttachmentService::class);
        $this->app->singleton(InboundEmailService::class);
        $this->app->singleton(SlaService::class);

        $this->bindRepositories();

        $this->app->singleton(HelpDeskManager::class, function ($app) {
            return new HelpDeskManager(
                $app->make(TicketRepository::class),
                $app->make(CommentRepository::class),
                $app->make(DepartmentRepository::class),
                $app->make(AttachmentRepository::class),
            );
        });
    }

    /**
     * Bind each repository contract to the configured driver.
     *
     * Deferred: the driver is read when a contract is first resolved, not at
     * registration, so a test can switch drivers and a config cache written
     * after boot still counts.
     */
    protected function bindRepositories(): void
    {
        $contracts = array_keys(self::DRIVERS['database']);

        foreach ($contracts as $contract) {
            $this->app->singleton($contract, function ($app) use ($contract) {
                return $app->make($this->implementationFor($contract));
            });
        }
    }

    /**
     * @param  class-string  $contract
     * @return class-string
     */
    protected function implementationFor(string $contract): string
    {
        $driver = config('help-desk.driver', 'database');

        if (! is_string($driver) || ! isset(self::DRIVERS[$driver])) {
            throw UnsupportedDriverException::make($driver, array_keys(self::DRIVERS));
        }

        return self::DRIVERS[$driver][$contract];
    }

    public function packageBooted(): void
    {
        if (config('help-desk.register_default_listeners', true)) {
            $this->registerEventListeners();
        }

        $this->registerApiRoutes();
    }

    /**
     * Only when at least one client is configured.
     *
     * A single application installation has no satellites to serve, so it gets
     * no endpoints — the surface that does not exist cannot be probed.
     */
    protected function registerApiRoutes(): void
    {
        $clients = config('help-desk.api.clients', []);

        if (! is_array($clients) || $clients === []) {
            return;
        }

        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
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

        // SLA due-date calculation
        Event::listen(TicketCreated::class, ApplySlaPolicy::class);
    }
}
