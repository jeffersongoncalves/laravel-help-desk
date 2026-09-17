<?php

namespace JeffersonGoncalves\HelpDesk\Exceptions;

use RuntimeException;

/**
 * Something went wrong talking to, or reasoning about, the central application.
 *
 * Each case names what to do instead, because the alternative is a raw SQL
 * error about a missing table or an unexplained 401 — the failures this whole
 * transport exists to make legible.
 */
class HelpDeskApiException extends RuntimeException
{
    /**
     * Reading a relation on a model that came back over the API.
     */
    public static function relationUnavailable(string $model, string $relation, ?string $alternative = null): self
    {
        $class = class_basename($model);

        return new self(sprintf(
            'Relation [%s] on %s needs the database driver. This model came back over the API, so there is nothing local to join against.%s',
            $relation,
            $class,
            $alternative ? ' '.$alternative : '',
        ));
    }

    /**
     * An operator action attempted from a satellite.
     */
    public static function operatorOnly(string $method): self
    {
        return new self(sprintf(
            '%s is an operator action and the API driver cannot perform it. It belongs to the central application, on the database driver.',
            $method,
        ));
    }

    public static function notConfigured(string $key): self
    {
        return new self("The API driver needs help-desk.{$key} to be set.");
    }

    public static function notImplemented(string $what): self
    {
        return new self("{$what} over the API is not implemented yet. Use the database driver, or wait for the attachment phase.");
    }

    public static function tooLargeInline(int $sizeInKb, int $limitInKb): self
    {
        return new self(
            "This file is {$sizeInKb} KB and the API sends attachments inline, which is capped at "
            ."{$limitInKb} KB. Raise help-desk.api.max_inline_attachment on both ends, or use the "
            .'database driver for files this size.',
        );
    }

    public static function noDisk(string $method): self
    {
        return new self(
            "{$method} is not available on the API driver: the file is on the central application's "
            .'disk, which this application has no credentials for. Use '
            .'HelpDesk::attachments()->contents($attachment) to fetch the bytes instead.',
        );
    }

    public static function noActor(): self
    {
        return new self(
            'The API driver could not tell who is acting. A read uses the authenticated user, '
            .'so either log someone in or call a method that takes the actor explicitly.',
        );
    }

    public static function unauthorized(): self
    {
        return new self('The central application rejected the signature. Check the app key, the shared secret, and that the two clocks agree.');
    }

    public static function unreachable(string $reason): self
    {
        return new self("Could not reach the central help desk: {$reason}");
    }

    /**
     * @param  array<string, mixed>  $errors
     */
    public static function invalid(array $errors): self
    {
        $first = collect($errors)->flatten()->first();

        return new self('The central application rejected the request: '.($first ?? 'validation failed.'));
    }

    public static function failed(int $status, string $body): self
    {
        return new self("The central help desk returned {$status}: ".mb_substr($body, 0, 200));
    }
}
