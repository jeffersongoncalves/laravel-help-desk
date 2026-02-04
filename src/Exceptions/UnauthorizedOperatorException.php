<?php

namespace JeffersonGoncalves\HelpDesk\Exceptions;

use RuntimeException;

class UnauthorizedOperatorException extends RuntimeException
{
    public static function forDepartment(int $departmentId): self
    {
        return new self("Operator is not authorized for department [{$departmentId}].");
    }

    public static function forTicket(int $ticketId): self
    {
        return new self("Operator is not authorized to manage ticket [{$ticketId}].");
    }
}
