<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class MeController extends Controller
{
    /**
     * GET /api/v1/me — current user, organization, roles and permissions
     * (FR-AUTH-003).
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user()->load(['organization', 'roles.permissions']);

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }
}
