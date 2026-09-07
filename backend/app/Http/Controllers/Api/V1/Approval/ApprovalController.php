<?php

namespace App\Http\Controllers\Api\V1\Approval;

use App\Actions\Approvals\CancelApproval;
use App\Actions\Approvals\RequestApproval;
use App\Actions\Approvals\RespondToApproval;
use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Approval\RespondApprovalRequest;
use App\Http\Requests\Approval\StoreApprovalRequest;
use App\Http\Resources\Api\V1\ApprovalResource;
use App\Models\Approval;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;

class ApprovalController extends Controller implements HasMiddleware
{
    private const SORTABLE = ['created_at', 'due_at', 'status'];

    private const WITH_FOR_LIST = [
        'serviceRequest:id,request_number,title',
        'requestedBy:id,name,email,status,timezone',
        'assignee:id,name,email,status,timezone',
    ];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:approval.view', only: ['index', 'show']),
            new Middleware('permission:request.view', only: ['store']),
            new Middleware('permission:approval.request', only: ['store']),
            new Middleware('permission:approval.respond', only: ['approve', 'reject']),
        ];
    }

    public function __construct(
        private readonly RequestApproval $requestApproval,
        private readonly RespondToApproval $respondToApproval,
        private readonly CancelApproval $cancelApproval,
    ) {}

    /**
     * GET /api/v1/approvals — paginated org-scoped approval list with status,
     * assignee and service request filters plus sortable columns
     * (created_at / due_at / status, default -created_at).
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 15);
        $perPage = min(max($perPage, 1), 50);

        [$sort, $direction] = $this->sort($request);

        $query = Approval::query()
            ->where('organization_id', $request->user()->organization_id)
            ->with(self::WITH_FOR_LIST);

        $this->filterStatuses($query, $request);

        if ($request->filled('assigned_to')) {
            $assignedTo = (string) $request->query('assigned_to');

            $query->where('assigned_to', $assignedTo === 'me' ? $request->user()->id : $assignedTo);
        }

        if ($request->filled('service_request_id')) {
            $query->where('service_request_id', (string) $request->query('service_request_id'));
        }

        $query->orderBy($sort, $direction)->orderBy('id');

        /** @var LengthAwarePaginator<int, Approval> $paginator */
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => ApprovalResource::collection($paginator->items()),
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
     * POST /api/v1/service-requests/{serviceRequest}/approvals — request an
     * approval from an org decision-maker (PRD §16/§23).
     */
    public function store(StoreApprovalRequest $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $this->assertRequestOrganization($serviceRequest, $request->user()->organization_id);

        /** @var User $assignee */
        $assignee = User::query()->findOrFail($request->validated('assigned_to'));

        $data = $request->validated();
        $dueAt = $data['due_at'] ?? null;

        $approval = $this->requestApproval->handle(
            $serviceRequest,
            $request->user(),
            $assignee,
            [
                'type' => ApprovalType::from($data['type']),
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'due_at' => is_string($dueAt) ? Carbon::parse($dueAt) : null,
            ],
        );

        return response()->json([
            'data' => new ApprovalResource($approval->load(self::WITH_FOR_LIST)),
        ], 201);
    }

    /**
     * GET /api/v1/approvals/{approval} — single approval detail with its
     * relations.
     */
    public function show(Request $request, Approval $approval): JsonResponse
    {
        $this->assertApprovalOrganization($approval, $request->user()->organization_id);

        return response()->json([
            'data' => new ApprovalResource(
                $approval->load([
                    'serviceRequest:id,request_number,title',
                    'requestedBy:id,name,email,status,timezone',
                    'assignee:id,name,email,status,timezone',
                    'attachments',
                ]),
            ),
        ]);
    }

    /**
     * POST /api/v1/approvals/{approval}/approve — the assigned decision-maker
     * approves the still-pending approval.
     */
    public function approve(RespondApprovalRequest $request, Approval $approval): JsonResponse
    {
        $this->authorizeResponder($approval, $request);

        $updated = $this->respondToApproval->handle(
            $approval,
            ApprovalStatus::Approved,
            $request->user(),
            $request->validated('response_note'),
        );

        return response()->json([
            'data' => new ApprovalResource($updated->load(self::WITH_FOR_LIST)),
        ]);
    }

    /**
     * POST /api/v1/approvals/{approval}/reject — the assigned decision-maker
     * rejects the still-pending approval.
     */
    public function reject(RespondApprovalRequest $request, Approval $approval): JsonResponse
    {
        $this->authorizeResponder($approval, $request);

        $updated = $this->respondToApproval->handle(
            $approval,
            ApprovalStatus::Rejected,
            $request->user(),
            $request->validated('response_note'),
        );

        return response()->json([
            'data' => new ApprovalResource($updated->load(self::WITH_FOR_LIST)),
        ]);
    }

    /**
     * POST /api/v1/approvals/{approval}/cancel — the user who requested the
     * approval or a manager holding approval.cancel withdraws a pending
     * approval. Authorization is decided here (requester ownership bypasses
     * the middleware permission) so requesters without approval.cancel can
     * still cancel their own approvals.
     */
    public function cancel(Request $request, Approval $approval): JsonResponse
    {
        $this->assertApprovalOrganization($approval, $request->user()->organization_id);

        /** @var User $user */
        $user = $request->user();

        abort_unless(
            $approval->requested_by === $user->id || $user->hasPermission('approval.cancel'),
            403,
        );

        $updated = $this->cancelApproval->handle($approval);

        return response()->json([
            'data' => new ApprovalResource($updated->load(self::WITH_FOR_LIST)),
        ]);
    }

    /**
     * The responder must be the user the approval was assigned to and belong
     * to the same organization (cross-org URLs 404, anyone else 403).
     */
    private function authorizeResponder(Approval $approval, Request $request): void
    {
        $this->assertApprovalOrganization($approval, $request->user()->organization_id);

        abort_unless($approval->assigned_to === $request->user()->id, 403);
    }

    /**
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
     * status[]=PENDING&status[]=APPROVED filters; unknown values are ignored.
     *
     * @param  Builder<Approval>  $query
     */
    private function filterStatuses(Builder $query, Request $request): void
    {
        if (! $request->filled('status')) {
            return;
        }

        $values = (array) $request->query('status');
        $valid = array_values(array_filter(
            array_map(
                static fn ($value): ?string => is_string($value)
                    ? ApprovalStatus::tryFrom(strtoupper($value))?->value
                    : null,
                $values,
            ),
            static fn (?string $value): bool => $value !== null,
        ));

        if ($valid !== []) {
            $query->whereIn('status', $valid);
        }
    }

    private function assertApprovalOrganization(Approval $approval, string $organizationId): void
    {
        abort_unless($approval->organization_id === $organizationId, 404);
    }

    private function assertRequestOrganization(ServiceRequest $serviceRequest, string $organizationId): void
    {
        abort_unless($serviceRequest->organization_id === $organizationId, 404);
    }
}
