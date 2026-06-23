<?php

use JeffersonGoncalves\HelpDesk\Tests\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(TestCase::class)->in('Feature');

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
