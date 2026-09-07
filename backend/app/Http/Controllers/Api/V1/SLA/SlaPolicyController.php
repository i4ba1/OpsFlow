<?php

namespace App\Http\Controllers\Api\V1\SLA;

use App\Http\Controllers\Controller;
use App\Http\Requests\SLA\StoreSlaPolicyRequest;
use App\Http\Requests\SLA\UpdateSlaPolicyRequest;
use App\Http\Resources\Api\V1\SlaPolicyResource;
use App\Models\SlaPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class SlaPolicyController extends Controller implements HasMiddleware
{
    private const WITH = ['targets', 'businessCalendar'];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:sla.view', only: ['index', 'show']),
            new Middleware('permission:sla.manage', only: ['store', 'update']),
        ];
    }

    /**
     * GET /api/v1/sla-policies — paginated org SLA policy list with nested
     * targets.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 15);
        $perPage = min(max($perPage, 1), 50);

        $query = SlaPolicy::query()
            ->where('organization_id', $request->user()->organization_id)
            ->with(self::WITH);

        if ($request->filled('active')) {
            $query->where('active', filter_var($request->query('active'), FILTER_VALIDATE_BOOL));
        }

        $query->orderBy('created_at')->orderBy('id');

        /** @var LengthAwarePaginator<int, SlaPolicy> $paginator */
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => SlaPolicyResource::collection($paginator->items()),
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
     * POST /api/v1/sla-policies — create a policy with its targets in one
     * transaction.
     */
    public function store(StoreSlaPolicyRequest $request): JsonResponse
    {
        $policy = DB::transaction(function () use ($request): SlaPolicy {
            $policy = SlaPolicy::query()->create([
                'organization_id' => $request->user()->organization_id,
                'name' => $request->validated('name'),
                'description' => $request->validated('description'),
                'timezone' => $request->validated('timezone', 'Asia/Singapore'),
                'business_calendar_id' => $request->validated('business_calendar_id'),
                'active' => (bool) $request->validated('active', true),
            ]);

            $this->syncTargets($policy, $request->validated('targets', []));

            return $policy;
        });

        return response()->json([
            'data' => new SlaPolicyResource($policy->load(self::WITH)),
        ], 201);
    }

    /**
     * GET /api/v1/sla-policies/{slaPolicy} — single org policy.
     */
    public function show(Request $request, SlaPolicy $slaPolicy): JsonResponse
    {
        $this->assertSameOrganization($slaPolicy, $request->user()->organization_id);

        return response()->json([
            'data' => new SlaPolicyResource($slaPolicy->load(self::WITH)),
        ]);
    }

    /**
     * PATCH /api/v1/sla-policies/{slaPolicy} — update the policy profile
     * and/or replace its targets.
     */
    public function update(UpdateSlaPolicyRequest $request, SlaPolicy $slaPolicy): JsonResponse
    {
        $this->assertSameOrganization($slaPolicy, $request->user()->organization_id);

        $updated = DB::transaction(function () use ($request, $slaPolicy): SlaPolicy {
            $data = $request->validated();

            $slaPolicy->update(collect($data)->except('targets')->all());

            if (isset($data['targets']) && is_array($data['targets'])) {
                $this->syncTargets($slaPolicy, $data['targets']);
            }

            return $slaPolicy;
        });

        return response()->json([
            'data' => new SlaPolicyResource($updated->load(self::WITH)),
        ]);
    }

    /**
     * Replace the targets of a policy from validated input. Priorities not in
     * the payload are removed so the stored target set always mirrors the
     * request.
     *
     * @param  list<array<string, mixed>>  $targets
     */
    private function syncTargets(SlaPolicy $policy, array $targets): void
    {
        $kept = [];

        foreach ($targets as $target) {
            if (! isset($target['priority'])) {
                continue;
            }

            $priority = (string) $target['priority'];

            $kept[] = $priority;

            $policy->targets()->updateOrCreate(
                ['sla_policy_id' => $policy->id, 'priority' => strtoupper($priority)],
                [
                    'first_response_minutes' => (int) $target['first_response_minutes'],
                    'resolution_minutes' => (int) $target['resolution_minutes'],
                    'warning_threshold_percent' => (int) ($target['warning_threshold_percent'] ?? 75),
                ],
            );
        }

        if ($kept !== []) {
            $policy->targets()
                ->whereNotIn('priority', $kept)
                ->delete();
        }
    }

    private function assertSameOrganization(SlaPolicy $slaPolicy, string $organizationId): void
    {
        abort_unless($slaPolicy->organization_id === $organizationId, 404);
    }
}
