<?php

use JeffersonGoncalves\HelpDesk\Tests\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(TestCase::class)->in('Feature');

/**
 * Reset the package settings the suite writes to.
 *
 * Testbench reuses the application between some tests, so a value one test sets
 * survives into the next. That already caused a CI failure visible only on MySQL
 * and Postgres, where a leaked connection name reached the next test's
 * migrations, and a leaked "help-desk.app.key" would silently scope another
 * file's queries under a random test order.
 *
 * This has to be afterEach. Pest runs beforeEach from inside setUp(), by which
 * point the migrations for that test have already run against whatever the
 * previous test left behind.
 *
 * Every setting a test writes belongs here.
 */
afterEach(function () {
    config()->set('help-desk.connection', null);
    config()->set('help-desk.app.key', null);
    config()->set('help-desk.app.name', null);
    config()->set('help-desk.scope_to_app', false);
});

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
