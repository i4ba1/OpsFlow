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

class ActivateUserController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:user.disable'),
        ];
    }

    /**
     * POST /api/v1/users/{user}/activate — activate a disabled user
     * (FR-USER-005).
     */
    public function __invoke(Request $request, User $user): JsonResponse
    {
        abort_unless($user->organization_id === $request->user()->organization_id, 404);

        $user->update(['status' => UserStatus::Active]);

        return response()->json([
            'data' => new UserResource($user->fresh(['roles'])),
        ]);
    }
}
