<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Thrown when deleting a role that is still assigned to users (409, error
 * code ROLE_IN_USE).
 */
final class RoleInUseException extends HttpException
{
    public function __construct()
    {
        parent::__construct(409, 'This role is still assigned to users.');
    }
}
