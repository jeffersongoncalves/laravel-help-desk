<?php

namespace JeffersonGoncalves\HelpDesk\Exceptions;

use RuntimeException;

class EmailProcessingException extends RuntimeException
{
    public static function parsingFailed(string $reason): self
    {
        return new self("Failed to parse inbound email: {$reason}");
    }

    public static function driverNotInstalled(string $driver, string $package): self
    {
        return new self(
            __('help-desk::emails.errors.driver_not_installed', [
                'driver' => $driver,
                'package' => $package,
            ])
        );
    }

    public static function connectionFailed(string $error): self
    {
        return new self(
            __('help-desk::emails.errors.connection_failed', [
                'error' => $error,
            ])
        );
    }
}
