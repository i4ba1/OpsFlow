<?php

namespace App\Http\Controllers\Api\V1\Audit;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class AuditLogController extends Controller implements HasMiddleware
{
    private const SORTABLE = ['created_at', 'action', 'entity_type'];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:audit.view', only: ['index', 'show']),
        ];
    }

    /**
     * GET /api/v1/audit-logs — paginated, filterable audit trail of the
     * active organization (PRD §19, §23).
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 25);
        $perPage = min(max($perPage, 1), 100);

        $sort = (string) $request->query('sort', '-created_at');
        $direction = 'desc';

        $column = ltrim($sort, '-+');

        if (! in_array($column, self::SORTABLE, true)) {
            $column = 'created_at';
        }

        if (! str_starts_with($sort, '-')) {
            $direction = strtolower((string) $request->query('direction', 'asc'));
            $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'asc';
        }

        $query = AuditLog::query()
            ->with('actor:id,name,email')
            ->where('organization_id', $request->user()->organization_id);

        if ($request->filled('action')) {
            $query->where('action', (string) $request->query('action'));
        }

        if ($request->filled('entity_type')) {
            $query->where('entity_type', (string) $request->query('entity_type'));
        }

        if ($request->filled('entity_id')) {
            $query->where('entity_id', (string) $request->query('entity_id'));
        }

        if ($request->filled('actor_id')) {
            $query->where('actor_id', (string) $request->query('actor_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', (string) $request->query('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', (string) $request->query('date_to'));
        }

        $query->orderBy($column, $direction);

        /** @var LengthAwarePaginator<int, AuditLog> $paginator */
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => AuditLogResource::collection($paginator->items()),
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
     * GET /api/v1/audit-logs/{auditLog} — a single audit entry.
     */
    public function show(Request $request, AuditLog $auditLog): JsonResponse
    {
        abort_unless($auditLog->organization_id === $request->user()->organization_id, 404);

        return response()->json([
            'data' => new AuditLogResource($auditLog->load('actor:id,name,email')),
        ]);
    }
}
