<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Thrown when an attempt is made to edit fields of a service request that is
 * in a terminal (RESOLVED / CLOSED / CANCELLED) state (422, error code
 * REQUEST_NOT_EDITABLE).
 */
final class RequestNotEditableException extends HttpException
{
    public function __construct(string $message = 'A resolved, closed or cancelled request cannot be edited.')
    {
        parent::__construct(422, $message);
    }
}
