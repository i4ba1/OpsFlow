<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class UserController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:user.view', only: ['index', 'show']),
            new Middleware('permission:user.create', only: ['store']),
            new Middleware('permission:user.update', only: ['update']),
        ];
    }

    /**
     * GET /api/v1/users — paginated, searchable and sortable org user list
     * (FR-USER-001).
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 15);
        $perPage = min(max($perPage, 1), 50);

        $sort = (string) $request->query('sort', 'created_at');
        if (! in_array($sort, ['name', 'email', 'status', 'created_at'], true)) {
            $sort = 'created_at';
        }

        $direction = strtolower((string) $request->query('direction', 'desc'));
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc';

        $query = User::query()
            ->where('organization_id', $request->user()->organization_id)
            ->with('roles');

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));

            if ($search !== '') {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }
        }

        if ($request->filled('status')) {
            $status = strtoupper((string) $request->query('status'));

            if (UserStatus::tryFrom($status) !== null) {
                $query->where('status', $status);
            }
        }

        $query->orderBy($sort, $direction);

        /** @var LengthAwarePaginator<int, User> $paginator */
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => UserResource::collection($paginator->items()),
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
     * POST /api/v1/users — invite a user (FR-USER-002).
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request): User {
            $user = User::query()->create([
                'organization_id' => $request->user()->organization_id,
                'name' => $request->validated('name'),
                'email' => strtolower($request->validated('email')),
                'status' => UserStatus::Invited,
                'timezone' => $request->user()->timezone,
            ]);

            if (! empty($request->validated('role_ids'))) {
                $roleIds = $this->sameOrganizationRoleIds(
                    (array) $request->validated('role_ids'),
                    $request->user()->organization_id,
                );
                $user->roles()->attach($roleIds);
            }

            return $user;
        });

        return response()->json([
            'data' => new UserResource($user->load('roles')),
        ], 201);
    }

    /**
     * GET /api/v1/users/{user} — single org user (FR-USER-003).
     */
    public function show(Request $request, User $user): JsonResponse
    {
        $this->assertSameOrganization($user, $request->user()->organization_id);

        return response()->json([
            'data' => new UserResource($user->load(['roles', 'organization'])),
        ]);
    }

    /**
     * PATCH /api/v1/users/{user} — update a user profile (FR-USER-004).
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->assertSameOrganization($user, $request->user()->organization_id);

        $data = $request->validated();

        if (array_key_exists('email', $data)) {
            $data['email'] = strtolower($data['email']);
        }

        $user->update($data);

        return response()->json([
            'data' => new UserResource($user->fresh(['roles'])),
        ]);
    }

    /**
     * @param  list<string>  $roleIds
     * @return list<string>
     */
    private function sameOrganizationRoleIds(array $roleIds, string $organizationId): array
    {
        return Role::query()
            ->where('organization_id', $organizationId)
            ->whereIn('id', $roleIds)
            ->pluck('id')
            ->all();
    }

    private function assertSameOrganization(User $user, string $organizationId): void
    {
        abort_unless($user->organization_id === $organizationId, 404);
    }
}
