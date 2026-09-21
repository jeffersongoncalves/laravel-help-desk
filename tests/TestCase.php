<?php

namespace JeffersonGoncalves\HelpDesk\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use JeffersonGoncalves\HelpDesk\HelpDeskServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    /**
     * Same order as HelpDeskServiceProvider::hasMigrations(). SQLite doesn't
     * enforce foreign keys at CREATE TABLE time, but MySQL/Postgres do, so
     * migrations must run in dependency order, not alphabetically by stub name.
     */
    private const MIGRATION_ORDER = [
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
        'add_company_id_to_help_desk_tickets_table',
        'add_metadata_to_help_desk_ticket_watchers_table',
        'create_help_desk_ticket_feedback_table',
        'create_help_desk_sla_policies_table',
        'add_sla_to_help_desk_tickets_table',
        'add_sla_pause_to_help_desk_tickets_table',
        'add_sla_breach_tracking_to_help_desk_tickets_table',
        'create_help_desk_automation_rules_table',
        'create_help_desk_ticket_automations_applied_table',
        'create_help_desk_kb_articles_table',
        'change_settings_to_text_in_help_desk_email_channels_table',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'JeffersonGoncalves\\HelpDesk\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app): array
    {
        return [
            HelpDeskServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', $this->testing_connection());

        // A second name for the same database, for the tests that point
        // help-desk.connection somewhere other than the default. Defined here
        // rather than in a test hook so it exists for the whole life of the
        // application: Testbench reuses the application between some tests, and
        // a connection a hook defines and tears down is missing during the
        // setUp of the next test that reuses it.
        $app['config']->set('database.connections.help_desk_central', $this->testing_connection());

        // Routes register at boot and only when a client exists, so the clients
        // have to be here rather than in a beforeEach.
        $app['config']->set('help-desk.api.clients', [
            'app-a' => ['secrets' => ['app-a-secret'], 'actor_types' => ['app-a-user']],
            'app-b' => ['secrets' => ['app-b-secret'], 'actor_types' => ['app-b-user']],
        ]);

        $app['config']->set('help-desk.models.user', TestUser::class);
        $app['config']->set('help-desk.models.operator', TestUser::class);
        $app['config']->set('help-desk.register_default_listeners', false);
    }

    /**
     * Defaults to an in-memory SQLite connection for local development; CI
     * (tests.yml) sets HELP_DESK_TEST_DB_* to run the same suite against
     * real MySQL and PostgreSQL instances too. Deliberately not the plain
     * DB_* names: Orchestra Testbench itself sets DB_CONNECTION=testing by
     * convention, which would collide with (and always win over) a driver
     * value read from the same variable here.
     *
     * @return array<string, mixed>
     */
    protected function testing_connection(): array
    {
        $driver = env('HELP_DESK_TEST_DB_DRIVER', 'sqlite');

        if ($driver === 'sqlite') {
            return ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''];
        }

        return [
            'driver' => $driver,
            'host' => env('HELP_DESK_TEST_DB_HOST', '127.0.0.1'),
            'port' => env('HELP_DESK_TEST_DB_PORT'),
            'database' => env('HELP_DESK_TEST_DB_DATABASE', 'testing'),
            'username' => env('HELP_DESK_TEST_DB_USERNAME', 'root'),
            'password' => env('HELP_DESK_TEST_DB_PASSWORD', ''),
            'charset' => $driver === 'pgsql' ? 'utf8' : 'utf8mb4',
            'prefix' => '',
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        $stubsPath = __DIR__.'/../database/migrations';
        $tempPath = sys_get_temp_dir().'/laravel-help-desk-migrations';

        // Stale files from a previous run of this suite on another branch
        // (different MIGRATION_ORDER, so a table ends up numbered
        // differently) would otherwise sit here and get loaded too --
        // loadMigrationsFrom() picks up every *.php file in the directory,
        // not just the ones this run wrote.
        if (is_dir($tempPath)) {
            array_map('unlink', glob($tempPath.'/*.php') ?: []);
        } else {
            mkdir($tempPath, 0755, true);
        }

        foreach (self::MIGRATION_ORDER as $index => $name) {
            copy($stubsPath.'/'.$name.'.php.stub', $tempPath.'/'.sprintf('%03d_%s.php', $index, $name));
        }

        $this->loadMigrationsFrom($tempPath);
    }
}
