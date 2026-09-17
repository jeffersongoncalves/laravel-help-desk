<?php

namespace JeffersonGoncalves\HelpDesk\Tests;

/**
 * A single application installation: no satellites, so no API clients.
 *
 * Routes are registered at boot, so the only way to assert that none appear is
 * to boot an application that never had a client configured.
 */
class NoApiClientsTestCase extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('help-desk.api.clients', []);
    }
}
