<?php

namespace App\Support\Http;

use App\Exceptions\RoleInUseException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Normalize API exceptions into the PRD §22.4 error envelope.
 */
final class ApiExceptionRenderer
{
    public function render(Request $request, Throwable $exception): Response
    {
        $status = $this->statusFor($exception);
        $code = $this->codeFor($exception, $status);

        $error = [
            'code' => $code,
            'message' => $exception->getMessage() ?: 'An unexpected error occurred.',
        ];

        if ($exception instanceof ValidationException) {
            $error['message'] = 'The submitted data is invalid.';
            $error['fields'] = $exception->errors();
        }

        if ($exception instanceof AuthorizationException) {
            $error['message'] = 'You are not authorized to perform this action.';
        }

        return response()->json([
            'error' => $error,
            'meta' => [
                'request_id' => $request->attributes->get('request_id'),
            ],
        ], $status);
    }

    private function statusFor(Throwable $exception): int
    {
        if ($exception instanceof AuthenticationException) {
            return 401;
        }
        if ($exception instanceof AuthorizationException) {
            return 403;
        }
        if ($exception instanceof ValidationException) {
            return 422;
        }
        if ($exception instanceof HttpExceptionInterface) {
            return $exception->getStatusCode();
        }

        return 500;
    }

    private function codeFor(Throwable $exception, int $status): string
    {
        if ($exception instanceof AuthenticationException) {
            return 'AUTH_UNAUTHENTICATED';
        }
        if ($exception instanceof AuthorizationException) {
            return 'FORBIDDEN';
        }
        if ($exception instanceof ValidationException) {
            return 'VALIDATION_ERROR';
        }
        if ($exception instanceof HttpExceptionInterface) {
            if ($exception instanceof RoleInUseException) {
                return 'ROLE_IN_USE';
            }

            $messages = [
                403 => 'FORBIDDEN',
                404 => 'RESOURCE_NOT_FOUND',
                409 => 'RESOURCE_VERSION_CONFLICT',
                429 => 'RATE_LIMITED',
            ];

            return $messages[$status] ?? 'HTTP_ERROR';
        }

        return 'INTERNAL_ERROR';
    }
}
