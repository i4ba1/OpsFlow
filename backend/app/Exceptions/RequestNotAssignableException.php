<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Thrown when a service request cannot be assigned because it is in a
 * terminal state (422, error code REQUEST_NOT_ASSIGNABLE).
 */
final class RequestNotAssignableException extends HttpException
{
    public function __construct(string $message = 'This request cannot be assigned in its current state.')
    {
        parent::__construct(422, $message);
    }
}
