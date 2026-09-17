<?php

namespace JeffersonGoncalves\HelpDesk\Exceptions;

use RuntimeException;

class UnsupportedDriverException extends RuntimeException
{
    /**
     * @param  list<string>  $supported
     */
    public static function make(mixed $driver, array $supported): self
    {
        $given = is_string($driver) ? "'{$driver}'" : get_debug_type($driver);

        return new self(sprintf(
            'Unsupported help desk driver %s. Set help-desk.driver to one of: %s.',
            $given,
            implode(', ', $supported),
        ));
    }
}
