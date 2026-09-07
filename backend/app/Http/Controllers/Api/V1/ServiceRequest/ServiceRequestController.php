<?php

namespace App\Http\Controllers\Api\V1\ServiceRequest;

use App\Actions\ServiceRequests\AssignServiceRequest;
use App\Actions\ServiceRequests\ChangeServiceRequestPriority;
use App\Actions\ServiceRequests\CreateServiceRequest;
use App\Actions\ServiceRequests\TransitionServiceRequest;
use App\Actions\ServiceRequests\UpdateServiceRequest;
use App\Domain\ServiceRequests\ServiceRequestWorkflow;
use App\Enums\ServiceRequestPriority;
use App\Enums\ServiceRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceRequest\AssignServiceRequestRequest;
use App\Http\Requests\ServiceRequest\ChangeServiceRequestPriorityRequest;
use App\Http\Requests\ServiceRequest\StoreServiceRequestRequest;
use App\Http\Requests\ServiceRequest\TransitionServiceRequestRequest;
use App\Http\Requests\ServiceRequest\UpdateServiceRequestRequest;
use App\Http\Resources\Api\V1\ServiceRequestEventResource;
use App\Http\Resources\Api\V1\ServiceRequestResource;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ServiceRequestController extends Controller implements HasMiddleware
{
    private const SORTABLE = [
        'created_at',
        'updated_at',
        'priority',
        'status',
        'request_number',
        'resolution_due_at',
        'first_response_due_at',
    ];

    private const WITH_FOR_LIST = [
        'customer:id,name,customer_code',
        'project:id,name,project_code,customer_id',
        'category:id,name,color',
        'requestedBy:id,name,email,status,timezone',
        'assignee:id,name,email,status,timezone',
    ];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:request.view', only: ['index', 'show', 'history']),
            new Middleware('permission:request.create', only: ['store']),
            new Middleware('permission:request.update', only: ['update']),
            new Middleware('permission:request.assign', only: ['assign']),
            new Middleware('permission:request.change_priority', only: ['priority']),
            new Middleware('permission:request.transition', only: ['transition']),
        ];
    }

    public function __construct(
        private readonly CreateServiceRequest $createServiceRequest,
        private readonly UpdateServiceRequest $updateServiceRequest,
        private readonly AssignServiceRequest $assignServiceRequest,
        private readonly ChangeServiceRequestPriority $changeServiceRequestPriority,
        private readonly TransitionServiceRequest $transitionServiceRequest,
        private readonly ServiceRequestWorkflow $workflow,
    ) {}

    /**
     * GET /api/v1/service-requests — paginated, searchable, filterable and
     * sortable org request list. Sorting supports a leading '-' (e.g.
     * sort=-created_at) to request descending order.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 15);
        $perPage = min(max($perPage, 1), 50);

        [$sort, $direction] = $this->sort($request);

        $query = ServiceRequest::query()
            ->where('organization_id', $request->user()->organization_id)
            ->with(self::WITH_FOR_LIST);

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));

            if ($search !== '') {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('request_number', 'like', "%{$search}%");
                });
            }
        }

        $this->filterEnums($query, $request, 'status', ServiceRequestStatus::class);
        $this->filterEnums($query, $request, 'priority', ServiceRequestPriority::class);

        foreach (['assigned_to', 'customer_id', 'project_id', 'category_id'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, (string) $request->query($field));
            }
        }

        $query->orderBy($sort, $direction)->orderBy('id');

        /** @var LengthAwarePaginator<int, ServiceRequest> $paginator */
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => ServiceRequestResource::collection($paginator->items()),
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
     * POST /api/v1/service-requests — create a request for an org customer,
     * auto-numbered REQ-{year}-{000001}, starting in NEW status and requested
     * by the authenticated user.
     */
    public function store(StoreServiceRequestRequest $request): JsonResponse
    {
        $serviceRequest = $this->createServiceRequest->handle($request->user(), $request->validated());

        return response()->json([
            'data' => new ServiceRequestResource($serviceRequest->load(self::WITH_FOR_LIST)),
        ], 201);
    }

    /**
     * GET /api/v1/service-requests/{serviceRequest} — request detail with its
     * relations and the current version for optimistic locking.
     */
    public function show(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $this->assertSameOrganization($serviceRequest, $request->user()->organization_id);

        return response()->json([
            'data' => new ServiceRequestResource($serviceRequest->load(self::WITH_FOR_LIST)),
        ]);
    }

    /**
     * PATCH /api/v1/service-requests/{serviceRequest} — update title,
     * description or category while the request is still mutable. Sending the
     * observed version guards against concurrent edits (409 on mismatch).
     */
    public function update(UpdateServiceRequestRequest $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $this->assertSameOrganization($serviceRequest, $request->user()->organization_id);

        $data = $request->validated();
        $version = $data['version'] ?? null;
        unset($data['version']);

        $updated = $this->updateServiceRequest->handle(
            $serviceRequest,
            $data,
            is_int($version) ? $version : null,
        );

        return response()->json([
            'data' => new ServiceRequestResource($updated->load(self::WITH_FOR_LIST)),
        ]);
    }

    /**
     * POST /api/v1/service-requests/{serviceRequest}/assign — assign the
     * request to an org user.
     */
    public function assign(AssignServiceRequestRequest $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $this->assertSameOrganization($serviceRequest, $request->user()->organization_id);

        /** @var User $assignee */
        $assignee = User::query()->findOrFail($request->validated('assigned_to'));

        $updated = $this->assignServiceRequest->handle(
            $serviceRequest,
            $assignee,
            $request->user(),
            $request->validated('note'),
        );

        return response()->json([
            'data' => new ServiceRequestResource($updated->load(self::WITH_FOR_LIST)),
        ]);
    }

    /**
     * POST /api/v1/service-requests/{serviceRequest}/priority — change the
     * priority of a mutable request.
     */
    public function priority(ChangeServiceRequestPriorityRequest $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $this->assertSameOrganization($serviceRequest, $request->user()->organization_id);

        $updated = $this->changeServiceRequestPriority->handle(
            $serviceRequest,
            ServiceRequestPriority::from($request->validated('priority')),
            $request->user(),
        );

        return response()->json([
            'data' => new ServiceRequestResource($updated->load(self::WITH_FOR_LIST)),
        ]);
    }

    /**
     * POST /api/v1/service-requests/{serviceRequest}/transition — walk the
     * state machine (PRD §12.2). The generic route requires request.transition;
     * edges that need a dedicated capability (resolve/close/reopen) additionally
     * require it.
     */
    public function transition(TransitionServiceRequestRequest $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $this->assertSameOrganization($serviceRequest, $request->user()->organization_id);

        /** @var User $user */
        $user = $request->user();

        $target = ServiceRequestStatus::from($request->validated('status'));

        abort_unless(
            $user->hasPermission($this->workflow->permissionFor($serviceRequest->status, $target)),
            403,
        );

        $updated = $this->transitionServiceRequest->handle(
            $serviceRequest,
            $target,
            $user,
            $request->validated('note'),
            $request->validated('version'),
        );

        return response()->json([
            'data' => new ServiceRequestResource($updated->load(self::WITH_FOR_LIST)),
        ]);
    }

    /**
     * GET /api/v1/service-requests/{serviceRequest}/history — the unified
     * request timeline: status transitions, assignments and priority changes
     * in chronological order (PRD §66).
     */
    public function history(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $this->assertSameOrganization($serviceRequest, $request->user()->organization_id);

        $events = $serviceRequest->events()->with('actor:id,name')->get();

        return response()->json([
            'data' => ServiceRequestEventResource::collection($events),
            'meta' => [
                'request_id' => $request->attributes->get('request_id'),
            ],
        ]);
    }

    /**
     * Parse the sort query parameter. Supports both a leading '-'/'+' prefix
     * (PRD §21, e.g. sort=-created_at) and the legacy direction parameter.
     *
     * @return array{0: string, 1: string}
     */
    private function sort(Request $request): array
    {
        $sort = (string) $request->query('sort', '-created_at');
        $direction = '';

        if (str_starts_with($sort, '-')) {
            $direction = 'desc';
            $sort = substr($sort, 1);
        } elseif (str_starts_with($sort, '+')) {
            $direction = 'asc';
            $sort = substr($sort, 1);
        }

        if (! in_array($sort, self::SORTABLE, true)) {
            $sort = 'created_at';
            $direction = '';
        }

        if ($direction === '') {
            $direction = strtolower((string) $request->query('direction', 'desc'));
            $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc';
        }

        return [$sort, $direction];
    }

    /**
     * @param  Builder<ServiceRequest>  $query
     * @param  class-string<\BackedEnum>  $enum
     */
    private function filterEnums(Builder $query, Request $request, string $field, string $enum): void
    {
        if (! $request->filled($field)) {
            return;
        }

        $values = (array) $request->query($field);
        $valid = array_values(array_filter(
            array_map(
                static fn ($value): ?string => is_string($value)
                    ? $enum::tryFrom(strtoupper($value))?->value
                    : null,
                $values,
            ),
            static fn (?string $value): bool => $value !== null,
        ));

        if ($valid !== []) {
            $query->whereIn($field, $valid);
        }
    }

    private function assertSameOrganization(ServiceRequest $serviceRequest, string $organizationId): void
    {
        abort_unless($serviceRequest->organization_id === $organizationId, 404);
    }
}
