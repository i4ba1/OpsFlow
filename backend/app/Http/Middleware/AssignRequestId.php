<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class AssignRequestId
{
    /**
     * Propagate a caller-supplied X-Request-ID header or mint a new UUIDv7 so
     * every API response can be correlated (PRD §22.2).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) $request->headers->get('X-Request-ID', (string) Str::uuid7());

        $request->headers->set('X-Request-ID', $requestId);
        $request->attributes->set('request_id', $requestId);

        $response = $next($request);
        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }
}
