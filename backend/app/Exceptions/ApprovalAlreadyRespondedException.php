<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Thrown when an approval is responded to or cancelled after a decision has
 * already been recorded — approval decisions are immutable (422, error code
 * APPROVAL_ALREADY_RESPONDED).
 */
final class ApprovalAlreadyRespondedException extends HttpException
{
    public function __construct(string $message = 'This approval has already been responded to.')
    {
        parent::__construct(422, $message);
    }
}
