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
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('help-desk.models.user', TestUser::class);
        $app['config']->set('help-desk.models.operator', TestUser::class);
        $app['config']->set('help-desk.register_default_listeners', false);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        $migrationPath = __DIR__.'/../database/migrations';
        $files = glob($migrationPath.'/*.php.stub');

        foreach ($files as $file) {
            $migrationFile = $migrationPath.'/'.basename($file, '.stub');

            if (! file_exists($migrationFile)) {
                copy($file, $migrationFile);
            }
        }

        $this->loadMigrationsFrom($migrationPath);

        $this->beforeApplicationDestroyed(function () use ($migrationPath, $files) {
            foreach ($files as $file) {
                $migrationFile = $migrationPath.'/'.basename($file, '.stub');

                if (file_exists($migrationFile)) {
                    unlink($migrationFile);
                }
            }
        });
    }
}
