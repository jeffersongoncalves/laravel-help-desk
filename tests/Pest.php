<?php

use JeffersonGoncalves\HelpDesk\Tests\NoApiClientsTestCase;
use JeffersonGoncalves\HelpDesk\Tests\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Reset the package settings the suite writes to, after every Feature test.
 *
 * Testbench reuses the application between some tests, so a value one test sets
 * survives into the next. That already caused a CI failure visible only on MySQL
 * and Postgres, where a leaked connection name reached the next test's
 * migrations, and a leaked "help-desk.app.key" would silently scope another
 * file's queries under a random test order.
 *
 * It has to be afterEach: Pest runs beforeEach from inside setUp(), by which
 * point the migrations for that test have already run against whatever the
 * previous test left behind.
 *
 * And it has to be chained onto uses(), not written as a standalone
 * afterEach(). A standalone hook in this file never fires - verified against
 * Pest 4.7.8 by having one write to a file and finding nothing written. Chaining
 * it here also scopes it to Feature, which is what we want: Unit tests boot no
 * application, so config() would have nothing to resolve.
 *
 * Every setting a test writes belongs in here.
 */
uses(TestCase::class)
    ->afterEach(function () {
        config()->set('help-desk.connection', null);
        config()->set('help-desk.app.key', null);
        config()->set('help-desk.app.name', null);
        config()->set('help-desk.scope_to_app', false);
    })
    ->in('Feature');

uses(NoApiClientsTestCase::class)->in('SingleApplication');

/**
 * Assert that the given callback aborts with the expected HTTP status code.
 */
function assertAbortsWith(Closure $callback, int $status): void
{
    try {
        $callback();
        test()->fail("Expected an HttpException with status {$status}, but none was thrown.");
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe($status);
    }
}
