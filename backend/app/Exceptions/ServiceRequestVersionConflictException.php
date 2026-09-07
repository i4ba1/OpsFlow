<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Thrown when an optimistic-lock version supplied by the client no longer
 * matches the persisted request version (409, error code
 * RESOURCE_VERSION_CONFLICT).
 */
final class ServiceRequestVersionConflictException extends HttpException
{
    public function __construct()
    {
        parent::__construct(409, 'This request was modified by another user. Refresh and try again.');
    }
}
