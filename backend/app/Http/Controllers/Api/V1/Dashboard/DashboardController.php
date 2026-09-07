<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Controller;
use App\Queries\Dashboard\DashboardQueries;
use App\Support\Dashboard\DashboardCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Cache;

/**
 * Read-only org-scoped dashboard endpoints (PRD §20, §23). Every payload is
 * computed from SQL aggregations by DashboardQueries and cached for 60 seconds
 * per organization; pass ?refresh=1 to bypass the cache on the next read (PRD
 * §67).
 */
class DashboardController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:dashboard.view', only: ['overview', 'requestVolume', 'sla']),
            // Manager dashboard data (PRD §20.2) additionally needs report.view.
            new Middleware('permission:dashboard.view', only: ['teamPerformance']),
            new Middleware('permission:report.view', only: ['teamPerformance']),
        ];
    }

    /**
     * GET /api/v1/dashboard/overview — operations cards: open/critical
     * requests, SLA at-risk and breached counts, compliance and resolution
     * time statistics (PRD §20.1).
     */
    public function overview(Request $request): JsonResponse
    {
        $organizationId = $request->user()->organization_id;
        $this->forgetWhenRefreshing($request, $organizationId, DashboardCache::OVERVIEW);

        $data = Cache::remember(
            DashboardCache::key(DashboardCache::OVERVIEW, $organizationId),
            DashboardCache::TTL_SECONDS,
            fn (): array => DashboardQueries::overview($organizationId),
        );

        return $this->envelope($request, $data);
    }

    /**
     * GET /api/v1/dashboard/request-volume — requests per day for the last 30
     * days plus the current status and priority distributions.
     */
    public function requestVolume(Request $request): JsonResponse
    {
        $organizationId = $request->user()->organization_id;
        $this->forgetWhenRefreshing($request, $organizationId, DashboardCache::REQUEST_VOLUME);

        $data = Cache::remember(
            DashboardCache::key(DashboardCache::REQUEST_VOLUME, $organizationId),
            DashboardCache::TTL_SECONDS,
            fn (): array => DashboardQueries::requestVolume($organizationId),
        );

        return $this->envelope($request, $data);
    }

    /**
     * GET /api/v1/dashboard/sla — 30 day resolved vs met trend and the overall
     * compliance percentage.
     */
    public function sla(Request $request): JsonResponse
    {
        $organizationId = $request->user()->organization_id;
        $this->forgetWhenRefreshing($request, $organizationId, DashboardCache::SLA);

        $data = Cache::remember(
            DashboardCache::key(DashboardCache::SLA, $organizationId),
            DashboardCache::TTL_SECONDS,
            fn (): array => DashboardQueries::slaTrend($organizationId),
        );

        return $this->envelope($request, $data);
    }

    /**
     * GET /api/v1/dashboard/team-performance — per-assignee workload and
     * resolution performance (PRD §20.2). Requires dashboard.view and
     * report.view.
     */
    public function teamPerformance(Request $request): JsonResponse
    {
        $organizationId = $request->user()->organization_id;
        $this->forgetWhenRefreshing($request, $organizationId, DashboardCache::TEAM_PERFORMANCE);

        $data = Cache::remember(
            DashboardCache::key(DashboardCache::TEAM_PERFORMANCE, $organizationId),
            DashboardCache::TTL_SECONDS,
            fn (): array => DashboardQueries::teamPerformance($organizationId),
        );

        return $this->envelope($request, $data);
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function envelope(Request $request, array $data): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => [
                'request_id' => $request->attributes->get('request_id'),
            ],
        ]);
    }

    private function forgetWhenRefreshing(Request $request, string $organizationId, string $payload): void
    {
        if ((int) $request->query('refresh', 0) === 1) {
            Cache::forget(DashboardCache::key($payload, $organizationId));
        }
    }
}
