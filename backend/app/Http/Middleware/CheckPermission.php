<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CheckPermission
{
    /**
     * Authorize the request against a required permission string.
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        abort_unless($user !== null && $user->hasPermission($permission), 403);

        return $next($request);
    }
}
