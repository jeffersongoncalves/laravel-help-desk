<?php

namespace JeffersonGoncalves\HelpDesk\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use JeffersonGoncalves\HelpDesk\HelpDeskServiceProvider;
use JeffersonGoncalves\PestPluginDatabaseMigrations\LoadsPackageMigrations;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use LoadsPackageMigrations;
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
        $this->loadPackageMigrations(HelpDeskServiceProvider::class);
    }
}
