<?php

namespace App\Http\Controllers\Api\V1\Project;

use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Resources\Api\V1\ProjectResource;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProjectController extends Controller implements HasMiddleware
{
    private const SORTABLE = ['name', 'project_code', 'status', 'starts_on', 'ends_on', 'created_at'];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:project.view', only: ['index', 'show']),
            new Middleware('permission:project.create', only: ['store']),
            new Middleware('permission:project.update', only: ['update']),
            new Middleware('permission:project.archive', only: ['archive']),
        ];
    }

    /**
     * GET /api/v1/projects — paginated, searchable and sortable org project
     * list.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 15);
        $perPage = min(max($perPage, 1), 50);

        $sort = (string) $request->query('sort', 'created_at');
        if (! in_array($sort, self::SORTABLE, true)) {
            $sort = 'created_at';
        }

        $direction = strtolower((string) $request->query('direction', 'desc'));
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc';

        $query = Project::query()
            ->where('organization_id', $request->user()->organization_id)
            ->with(['customer:id,name,customer_code', 'manager:id,name,email']);

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));

            if ($search !== '') {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('project_code', 'like', "%{$search}%");
                });
            }
        }

        if ($request->filled('status')) {
            $statuses = (array) $request->query('status', []);
            $valid = array_values(array_filter(
                array_map(
                    static fn ($value): ?string => is_string($value)
                        ? ProjectStatus::tryFrom(strtoupper($value))?->value
                        : null,
                    $statuses,
                ),
                static fn (?string $value): bool => $value !== null,
            ));

            if ($valid !== []) {
                $query->whereIn('status', $valid);
            }
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', (string) $request->query('customer_id'));
        }

        $query->orderBy($sort, $direction);

        /** @var LengthAwarePaginator<int, Project> $paginator */
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => ProjectResource::collection($paginator->items()),
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
     * POST /api/v1/projects — create a project with an auto-generated code.
     */
    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = $this->createWithCode($request);

        return response()->json([
            'data' => new ProjectResource($project->load(['customer:id,name,customer_code', 'manager:id,name,email'])),
        ], 201);
    }

    /**
     * GET /api/v1/projects/{project} — single org project.
     */
    public function show(Request $request, Project $project): JsonResponse
    {
        $this->assertSameOrganization($project, $request->user()->organization_id);

        return response()->json([
            'data' => new ProjectResource($project->load(['customer:id,name,customer_code', 'manager:id,name,email'])),
        ]);
    }

    /**
     * PATCH /api/v1/projects/{project} — update project profile and status.
     */
    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        $this->assertSameOrganization($project, $request->user()->organization_id);

        $project->update($request->validated());

        return response()->json([
            'data' => new ProjectResource($project->fresh()->load(['customer:id,name,customer_code', 'manager:id,name,email'])),
        ]);
    }

    /**
     * POST /api/v1/projects/{project}/archive — soft archive: the project is
     * not deleted, only marked ARCHIVED.
     */
    public function archive(Request $request, Project $project): JsonResponse
    {
        $this->assertSameOrganization($project, $request->user()->organization_id);

        $project->update([
            'status' => ProjectStatus::Archived,
            'archived_at' => now(),
        ]);

        return response()->json([
            'data' => new ProjectResource($project->fresh()->load(['customer:id,name,customer_code', 'manager:id,name,email'])),
        ]);
    }

    /**
     * Insert the project inside a transaction, retrying with a freshly
     * generated code when the unique (organization_id, project_code)
     * constraint rejects a candidate so parallel creates cannot collide.
     */
    private function createWithCode(StoreProjectRequest $request): Project
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $code = 'PRJ-'.strtoupper(Str::random(8));

            try {
                return DB::transaction(function () use ($request, $code): Project {
                    return Project::query()->create([
                        'organization_id' => $request->user()->organization_id,
                        'customer_id' => $request->validated('customer_id'),
                        'project_code' => $code,
                        'name' => $request->validated('name'),
                        'description' => $request->validated('description'),
                        'status' => $request->validated('status', ProjectStatus::Draft->value),
                        'starts_on' => $request->validated('starts_on'),
                        'ends_on' => $request->validated('ends_on'),
                        'manager_id' => $request->validated('manager_id'),
                    ]);
                });
            } catch (QueryException) {
                // A concurrent create claimed this code — retry with a fresh one.
            }
        }

        abort(500, 'Unable to allocate a unique project code.');
    }

    private function assertSameOrganization(Project $project, string $organizationId): void
    {
        abort_unless($project->organization_id === $organizationId, 404);
    }
}
