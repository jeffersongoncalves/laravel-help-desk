<?php

namespace JeffersonGoncalves\HelpDesk\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use JeffersonGoncalves\HelpDesk\HelpDeskServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

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
        $app['config']->set('database.connections.testing', match (env('DB_CONNECTION', 'sqlite')) {
            'mysql' => [
                'driver' => 'mysql',
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', 3306),
                'database' => env('DB_DATABASE', 'testing'),
                'username' => env('DB_USERNAME', 'root'),
                'password' => env('DB_PASSWORD', ''),
                'prefix' => '',
            ],
            'pgsql' => [
                'driver' => 'pgsql',
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', 5432),
                'database' => env('DB_DATABASE', 'testing'),
                'username' => env('DB_USERNAME', 'postgres'),
                'password' => env('DB_PASSWORD', 'postgres'),
                'prefix' => '',
            ],
            default => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        });

        $app['config']->set('help-desk.models.user', TestUser::class);
        $app['config']->set('help-desk.models.operator', TestUser::class);
        $app['config']->set('help-desk.register_default_listeners', false);
    }

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
    ];

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        $migrationPath = __DIR__.'/../database/migrations';
        $files = [];

        foreach (self::MIGRATION_ORDER as $index => $name) {
            $migrationFile = $migrationPath.'/'.sprintf('%03d_%s.php', $index, $name);

            if (! file_exists($migrationFile)) {
                copy($migrationPath.'/'.$name.'.php.stub', $migrationFile);
            }

            $files[] = $migrationFile;
        }

        $this->loadMigrationsFrom($migrationPath);

        $this->beforeApplicationDestroyed(function () use ($files) {
            foreach ($files as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        });
    }
}
