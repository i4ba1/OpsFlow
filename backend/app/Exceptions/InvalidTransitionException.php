<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Thrown when a service request status transition is not allowed by the
 * workflow state machine (422, error code REQUEST_INVALID_TRANSITION).
 */
final class InvalidTransitionException extends HttpException
{
    public function __construct(string $message = 'This status transition is not allowed.')
    {
        parent::__construct(422, $message);
    }
}
