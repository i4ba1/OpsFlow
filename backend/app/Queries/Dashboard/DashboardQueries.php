<?php

namespace App\Queries\Dashboard;

use App\Enums\ServiceRequestPriority;
use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Support\Dashboard\DashboardCache;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * SQL aggregations behind the Phase 8 dashboard endpoints (PRD §20, §67).
 *
 * All queries are org-scoped and perform their aggregation in SQL rather than
 * loading request rows into PHP. The only rows fetched whole are the resolved
 * request timestamps used to compute the median resolution time, which SQLite
 * cannot express with percentile_cont(). Callers wrap every result set in
 * Cache::remember so repeated dashboard polls only hit the database at most
 * once per 60 seconds (PRD §67).
 */
final class DashboardQueries
{
    private const TERMINAL = [
        ServiceRequestStatus::Resolved->value,
        ServiceRequestStatus::Closed->value,
        ServiceRequestStatus::Cancelled->value,
    ];

    /**
     * The "within 4 hours of the due date" warning band. Wall-clock proxy for
     * the authoritative per-request SLA state derived by the SLA engine
     * (business-hours aware); documented as such on the endpoint.
     */
    private const AT_RISK_WINDOW_HOURS = 4;

    /**
     * Overview cards (PRD §20.1). All figures are org-scoped single-value SQL
     * aggregations.
     *
     * @return array<string, int|float|null>
     */
    public static function overview(string $organizationId): array
    {
        $counts = self::statusCounts($organizationId);

        $resolvedWithDue = 0;
        $met = 0;

        $now = now();

        foreach (self::resolvedWithDue($organizationId) as $row) {
            $resolvedWithDue++;

            if (Carbon::parse($row->resolved_at)->lessThanOrEqualTo(Carbon::parse($row->resolution_due_at))) {
                $met++;
            }
        }

        $table = self::resolutionDurations($organizationId);

        $resolvedToday = ServiceRequest::query()
            ->where('organization_id', $organizationId)
            ->where('status', ServiceRequestStatus::Resolved->value)
            ->whereDate('resolved_at', $now->toDateString())
            ->count();

        $atRisk = self::countInWindow($organizationId, $now, self::AT_RISK_WINDOW_HOURS);
        $breached = (int) $counts['breached'];

        return [
            'open_requests' => (int) $counts['open'],
            'critical_open' => $counts['open'] === 0 ? 0 : (int) DB::table('service_requests')
                ->where('organization_id', $organizationId)
                ->whereNotIn('status', self::TERMINAL)
                ->where('priority', ServiceRequestPriority::Critical->value)
                ->count(),
            'at_risk_sla' => $atRisk,
            'breached_sla' => $breached,
            'sla_compliance_percent' => $resolvedWithDue === 0 ? 100 : (int) round(($met / $resolvedWithDue) * 100),
            'resolved_today' => $resolvedToday,
            'median_resolution_hours' => self::median($table),
        ];
    }

    /**
     * Requests per calendar day for the trailing 30 days (oldest first), zero
     * filled, plus the current status and priority distributions.
     *
     * @return array{daily: list<array{date: string, count: int}>, status_counts: list<array{status: string, count: int}>, priority_counts: list<array{priority: string, count: int}>}
     */
    public static function requestVolume(string $organizationId, int $days = 30): array
    {
        return [
            'daily' => self::zeroFilledDaily(
                self::requestCountsByDay($organizationId, $days),
                $days,
            ),
            'status_counts' => self::groupedCounts($organizationId, 'status'),
            'priority_counts' => self::priorityCounts($organizationId),
        ];
    }

    /**
     * Resolved-request volume and the count resolved within the resolution due
     * date per day for the trailing 30 days, plus the overall compliance
     * percentage (100 when there is nothing to measure).
     *
     * @return array{trend: list<array{date: string, resolved: int, met: int}>, overall_compliance_percent: int}
     */
    public static function slaTrend(string $organizationId, int $days = 30): array
    {
        $since = self::bucketFloor($days);

        $rows = DB::table('service_requests')
            ->select([
                new Expression('date(resolved_at) as day'),
                new Expression('COUNT(*) as resolved'),
                new Expression('SUM(CASE WHEN resolved_at <= resolution_due_at THEN 1 ELSE 0 END) as met'),
            ])
            ->where('organization_id', $organizationId)
            ->whereNotNull('resolved_at')
            ->whereNotNull('resolution_due_at')
            ->where('resolved_at', '>=', $since)
            ->groupBy(new Expression('date(resolved_at)'))
            ->orderBy(new Expression('date(resolved_at)'))
            ->get()
            ->keyBy('day');

        $byDay = [];
        $resolved = 0;
        $met = 0;

        foreach ($rows as $row) {
            $byDay[$row->day] = [
                'date' => $row->day,
                'resolved' => (int) $row->resolved,
                'met' => (int) $row->met,
            ];
            $resolved += (int) $row->resolved;
            $met += (int) $row->met;
        }

        $trend = [];
        $cursor = $since->copy();

        for ($i = 0; $i < $days; $i++) {
            $date = $cursor->toDateString();
            $trend[] = $byDay[$date] ?? ['date' => $date, 'resolved' => 0, 'met' => 0];
            $cursor->addDay();
        }

        return [
            'trend' => $trend,
            'overall_compliance_percent' => $resolved === 0 ? 100 : (int) round(($met / $resolved) * 100),
        ];
    }

    /**
     * Per-assignee workload and resolution performance over every request of
     * the organization, internal users only (PRD §20.2). Averages are computed
     * in PHP over the small per-assignee dataset; counts come straight from
     * SQL.
     *
     * @return list<array{user_id: string, name: string, open_count: int, resolved_count: int, avg_resolution_hours: float|null, avg_first_response_hours: float|null}>
     */
    public static function teamPerformance(string $organizationId): array
    {
        /** @var Collection<int, ServiceRequest> $requests */
        $requests = ServiceRequest::query()
            ->select(['assigned_to', 'status', 'resolved_at', 'created_at', 'first_responded_at'])
            ->where('organization_id', $organizationId)
            ->whereNotNull('assigned_to')
            ->orderBy('assigned_to')
            ->get();

        $performance = $requests->groupBy('assigned_to');

        if ($performance->isEmpty()) {
            return [];
        }

        $names = DB::table('users')
            ->whereIn('id', $performance->keys()->all())
            ->pluck('name', 'id')
            ->all();

        $assignees = [];

        foreach ($performance as $assigneeId => $assigneeRequests) {
            /** @var Collection<int, ServiceRequest> $assigneeRequests */
            $assigneeRequests = $assigneeRequests;

            $resolved = $assigneeRequests->filter(
                static fn (ServiceRequest $request): bool => $request->resolved_at !== null && $request->created_at !== null,
            );

            $firstResponses = $assigneeRequests->filter(
                static fn (ServiceRequest $request): bool => $request->first_responded_at !== null && $request->created_at !== null,
            );

            $assignees[] = [
                'user_id' => (string) $assigneeId,
                'name' => (string) ($names[$assigneeId] ?? ''),
                'open_count' => $assigneeRequests->filter(static fn (ServiceRequest $request): bool => $request->isOpen())->count(),
                'resolved_count' => $resolved->count(),
                'avg_resolution_hours' => self::averageHours($resolved),
                'avg_first_response_hours' => self::averageHours($firstResponses),
            ];
        }

        usort($assignees, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $assignees;
    }

    /**
     * Invalidate the cached dashboard payloads of an organization. Called from
     * the controller when a client passes refresh=1.
     */
    public static function forget(string $organizationId): void
    {
        foreach (DashboardCache::keys($organizationId) as $key) {
            Cache::forget($key);
        }
    }

    /**
     * The single COUNT(*) per status aggregation the cards reuse.
     *
     * @return array{open: int, breached: int, resolved: int, closed: int, cancelled: int}
     */
    private static function statusCounts(string $organizationId): array
    {
        $counts = ['open' => 0, 'breached' => 0, 'resolved' => 0, 'closed' => 0, 'cancelled' => 0];

        $byStatus = DB::table('service_requests')
            ->select('status')
            ->selectRaw('COUNT(*) as total')
            ->where('organization_id', $organizationId)
            ->groupBy('status')
            ->pluck('total', 'status');

        foreach ($byStatus as $status => $total) {
            if (! in_array($status, self::TERMINAL, true)) {
                $counts['open'] += (int) $total;
            } elseif ($status === ServiceRequestStatus::Resolved->value) {
                $counts['resolved'] = (int) $total;
            } elseif ($status === ServiceRequestStatus::Closed->value) {
                $counts['closed'] = (int) $total;
            } elseif ($status === ServiceRequestStatus::Cancelled->value) {
                $counts['cancelled'] = (int) $total;
            }
        }

        $counts['breached'] = self::countInWindow($organizationId, now(), 0);

        return $counts;
    }

    /**
     * Open requests with a resolution due date whose remaining wall-clock time
     * is inside the warning band. withThreshold=0 counts the already breached
     * (due <= now) and withThreshold=4 the "due within the next 4 hours" proxy
     * for at-risk (PRD §20.1). The authoritative business-hours SLA state per
     * request stays in the SLA engine.
     */
    private static function countInWindow(string $organizationId, Carbon $now, int $hours): int
    {
        $query = DB::table('service_requests')
            ->where('organization_id', $organizationId)
            ->whereNotIn('status', self::TERMINAL)
            ->whereNotNull('resolution_due_at');

        if ($hours === 0) {
            // Breached: the due date has already passed for an open request.
            $query->where('resolution_due_at', '<=', $now);
        } else {
            // At risk: still open and due within the next $hours hours.
            $query
                ->where('resolution_due_at', '>', $now)
                ->where('resolution_due_at', '<=', $now->copy()->addHours($hours));
        }

        return (int) $query->count();
    }

    /**
     * resolved_at / resolution_due_at of every resolved or closed request that
     * has a resolution due date.
     *
     * @return array<int, object{resolved_at: string, resolution_due_at: string}>
     */
    private static function resolvedWithDue(string $organizationId): array
    {
        return DB::table('service_requests')
            ->select('resolved_at', 'resolution_due_at')
            ->where('organization_id', $organizationId)
            ->whereIn('status', [
                ServiceRequestStatus::Resolved->value,
                ServiceRequestStatus::Closed->value,
            ])
            ->whereNotNull('resolved_at')
            ->whereNotNull('resolution_due_at')
            ->get()
            ->all();
    }

    /**
     * Resolution durations in whole seconds of every resolved request that
     * carries a resolution due date, ascending. Computed in PHP so the query
     * stays portable across SQLite and PostgreSQL (no database-specific date
     * arithmetic).
     *
     * @return list<int>
     */
    private static function resolutionDurations(string $organizationId): array
    {
        return ServiceRequest::query()
            ->select('created_at', 'resolved_at')
            ->where('organization_id', $organizationId)
            ->whereIn('status', [
                ServiceRequestStatus::Resolved->value,
                ServiceRequestStatus::Closed->value,
            ])
            ->whereNotNull('resolved_at')
            ->whereNotNull('resolution_due_at')
            ->get()
            ->map(static function (ServiceRequest $request): int {
                $created = Carbon::parse($request->created_at);
                $resolved = Carbon::parse($request->resolved_at);

                return (int) abs($created->diffInSeconds($resolved));
            })
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $seconds  ascending resolution durations in seconds
     */
    private static function median(array $seconds): ?float
    {
        $count = count($seconds);

        if ($count === 0) {
            return null;
        }

        $middle = intdiv($count, 2);

        $medianSeconds = $count % 2 === 1
            ? $seconds[$middle]
            : ($seconds[$middle - 1] + $seconds[$middle]) / 2;

        return round($medianSeconds / 3600, 2);
    }

    /**
     * Created-request counts per calendar day for the trailing window, oldest
     * day first.
     *
     * @return array<string, int>
     */
    private static function requestCountsByDay(string $organizationId, int $days): array
    {
        $since = self::bucketFloor($days);

        return DB::table('service_requests')
            ->select([
                new Expression('date(created_at) as day'),
                new Expression('COUNT(*) as total'),
            ])
            ->where('organization_id', $organizationId)
            ->where('created_at', '>=', $since)
            ->groupBy(new Expression('date(created_at)'))
            ->pluck('total', 'day')
            ->map(static fn ($total): int => (int) $total)
            ->all();
    }

    /**
     * Fill every day of the trailing window so the charts always render a
     * complete 30 point series (PRD §20.1 charts).
     *
     * @param  array<string, int>  $counts
     * @return list<array{date: string, count: int}>
     */
    private static function zeroFilledDaily(array $counts, int $days): array
    {
        $daily = [];
        $cursor = self::bucketFloor($days)->copy();

        for ($i = 0; $i < $days; $i++) {
            $date = $cursor->toDateString();
            $daily[] = ['date' => $date, 'count' => $counts[$date] ?? 0];
            $cursor->addDay();
        }

        return $daily;
    }

    /**
     * Convert COUNT(*) grouped rows into the {status|priority, count} shape.
     *
     * @return list<array{status: string, count: int}>
     */
    private static function groupedCounts(string $organizationId, string $column): array
    {
        return DB::table('service_requests')
            ->select($column)
            ->selectRaw('COUNT(*) as total')
            ->where('organization_id', $organizationId)
            ->groupBy($column)
            ->orderBy($column)
            ->get()
            ->map(static fn (object $row): array => [
                'status' => (string) $row->{$column},
                'count' => (int) $row->total,
            ])
            ->all();
    }

    /**
     * @return list<array{priority: string, count: int}>
     */
    private static function priorityCounts(string $organizationId): array
    {
        return DB::table('service_requests')
            ->select('priority')
            ->selectRaw('COUNT(*) as total')
            ->where('organization_id', $organizationId)
            ->groupBy('priority')
            ->orderBy('priority')
            ->get()
            ->map(static fn (object $row): array => [
                'priority' => (string) $row->priority,
                'count' => (int) $row->total,
            ])
            ->all();
    }

    /**
     * Start of the trailing window (today - (days - 1)) at 00:00 UTC. All
     * timestamps are stored in UTC and the dashboard buckets by UTC date.
     */
    private static function bucketFloor(int $days): Carbon
    {
        return now()->startOfDay()->subDays($days - 1);
    }

    /**
     * Average resolved/first-response time in fractional hours over a
     * collection of requests that all carry the relevant timestamp pair.
     *
     * @param  Collection<int, ServiceRequest>  $requests
     */
    private static function averageHours(Collection $requests): ?float
    {
        if ($requests->isEmpty()) {
            return null;
        }

        $total = 0.0;

        foreach ($requests as $request) {
            $start = $request->created_at;
            $end = $request->resolved_at ?? $request->first_responded_at;

            if ($start === null || $end === null) {
                continue;
            }

            $total += max(0.0, (float) $start->diffInSeconds($end) / 3600);
        }

        $count = $requests->count();

        return $count === 0 ? null : round($total / $count, 2);
    }
}
