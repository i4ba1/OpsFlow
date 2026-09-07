<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class DisableUserController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:user.disable'),
        ];
    }

    /**
     * POST /api/v1/users/{user}/disable — disable an active user
     * (FR-USER-005).
     */
    public function __invoke(Request $request, User $user): JsonResponse
    {
        abort_unless($user->organization_id === $request->user()->organization_id, 404);

        // An administrator must not lock themselves out of the tenant.
        abort_if($user->id === $request->user()->id, 403, 'You cannot disable your own account.');

        $user->update(['status' => UserStatus::Disabled]);

        return response()->json([
            'data' => new UserResource($user->fresh(['roles'])),
        ]);
    }
}
