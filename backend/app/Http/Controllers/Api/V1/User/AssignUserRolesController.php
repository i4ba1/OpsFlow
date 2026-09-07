<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\AssignUserRolesRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class AssignUserRolesController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:user.manage_roles'),
        ];
    }

    /**
     * PUT /api/v1/users/{user}/roles — replace the roles assigned to a user
     * (FR-USER-006).
     */
    public function __invoke(AssignUserRolesRequest $request, User $user): JsonResponse
    {
        abort_unless($user->organization_id === $request->user()->organization_id, 404);

        $roleIds = $request->validated('role_ids');

        $roles = Role::query()
            ->where('organization_id', $user->organization_id)
            ->whereIn('id', $roleIds)
            ->get();

        // A role id that does not belong to this tenant is treated as not
        // found so cross-tenant role existence is never revealed (PRD §9.5).
        abort_unless($roles->count() === count($roleIds), 404);

        $user->roles()->sync($roles->pluck('id')->all());

        return response()->json([
            'data' => new UserResource($user->fresh()->load('roles')),
        ]);
    }
}
