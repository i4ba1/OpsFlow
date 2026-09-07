<?php

namespace App\Http\Controllers\Api\V1\Role;

use App\Exceptions\RoleInUseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Http\Resources\Api\V1\RoleResource;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RoleController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:role.view', only: ['index']),
            new Middleware('permission:role.create', only: ['store']),
            new Middleware('permission:role.update', only: ['update']),
            new Middleware('permission:role.delete', only: ['destroy']),
        ];
    }

    /**
     * GET /api/v1/roles — paginated org roles with permission names.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 15);
        $perPage = min(max($perPage, 1), 50);

        $query = Role::query()
            ->where('organization_id', $request->user()->organization_id)
            ->with('permissions')
            ->orderBy('created_at');

        /** @var LengthAwarePaginator<int, Role> $paginator */
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => RoleResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'request_id' => $request->attributes->get('request_id'),
            ],
        ]);
    }

    /**
     * POST /api/v1/roles — create an org role.
     */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = DB::transaction(function () use ($request): Role {
            $role = Role::query()->create([
                'organization_id' => $request->user()->organization_id,
                'name' => $request->validated('name'),
                'slug' => $this->makeSlug(
                    slug: $request->validated('slug'),
                    name: $request->validated('name'),
                    organizationId: $request->user()->organization_id,
                ),
                'description' => $request->validated('description'),
                'is_system' => false,
            ]);

            $role->permissions()->attach($request->validated('permissions', []));

            return $role;
        });

        return response()->json([
            'data' => new RoleResource($role->load('permissions')),
        ], 201);
    }

    /**
     * PATCH /api/v1/roles/{role} — update an org role. System role identity
     * (name/slug) is immutable; permissions of system roles are not exposed.
     */
    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        abort_unless($role->organization_id === $request->user()->organization_id, 404);

        if ($role->is_system) {
            // System roles are seeded tenant defaults: identity and
            // capabilities must stay stable (FR-ROLE-004).
            abort(403, 'System roles cannot be modified.');
        }

        $role->update([
            'name' => $request->validated('name', $role->name),
            'description' => $request->validated('description', $role->description),
        ]);

        if ($request->has('permissions')) {
            $role->permissions()->sync($request->validated('permissions', []));
        }

        return response()->json([
            'data' => new RoleResource($role->fresh()->load('permissions')),
        ]);
    }

    /**
     * DELETE /api/v1/roles/{role} — delete an org role.
     */
    public function destroy(Request $request, Role $role): JsonResponse
    {
        abort_unless($role->organization_id === $request->user()->organization_id, 404);

        abort_if($role->is_system, 403, 'System roles cannot be deleted.');

        $inUse = $role->users()->exists();

        if ($inUse) {
            throw new RoleInUseException;
        }

        $role->delete();

        return response()->json(null, 204);
    }

    /**
     * Resolve the role slug. When the client supplies one explicitly it must
     * be unique within the organization (422 otherwise); otherwise the slug is
     * derived from the name and suffixed until unique (FR-ROLE-002).
     */
    private function makeSlug(?string $slug, string $name, string $organizationId): string
    {
        if ($slug === null) {
            $base = Str::slug($name) ?: 'role';
            $candidate = $base;
            $suffix = 2;

            while ($this->slugExists($candidate, $organizationId)) {
                $candidate = $base.'-'.$suffix;
                $suffix++;
            }

            return $candidate;
        }

        $slug = Str::slug($slug);

        if ($this->slugExists($slug, $organizationId)) {
            throw ValidationException::withMessages([
                'slug' => ['A role with this slug already exists in this organization.'],
            ]);
        }

        return $slug;
    }

    private function slugExists(string $slug, string $organizationId): bool
    {
        return Role::query()
            ->where('organization_id', $organizationId)
            ->where('slug', $slug)
            ->exists();
    }
}
