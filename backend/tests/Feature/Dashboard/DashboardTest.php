<?php

use App\Enums\ServiceRequestPriority;
use App\Enums\ServiceRequestStatus;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Support\Dashboard\DashboardCache;
use Illuminate\Support\Carbon;

/**
 * Grant the given user a single permission through a fresh role, mirroring how
 * the permission middleware checks real DB rows.
 */
function grantDashboardPermission(User $user, string $permission): void
{
    $permissionModel = Permission::firstOrCreate(
        ['name' => $permission],
        ['group' => explode('.', $permission)[0], 'description' => ucfirst($permission)],
    );

    $role = Role::factory()->create(['organization_id' => $user->organization_id]);
    $role->permissions()->attach($permissionModel->name);

    $user->roles()->attach($role->id);
}

function makeDashboardViewer(Organization $org): User
{
    $user = User::factory()->create(['organization_id' => $org->id]);
    grantDashboardPermission($user, 'dashboard.view');

    return $user;
}

function makeDashboardManager(Organization $org): User
{
    $user = User::factory()->create(['organization_id' => $org->id]);
    grantDashboardPermission($user, 'dashboard.view');
    grantDashboardPermission($user, 'report.view');

    return $user;
}

function dashboardOrganization(): Organization
{
    return Organization::factory()->create();
}

function dashboardCustomer(Organization $org): Customer
{
    return Customer::factory()->create(['organization_id' => $org->id]);
}

function dashboardToken(User $user): string
{
    return $user->createToken('spa')->plainTextToken;
}

/**
 * A resolved request stamped with its created_at/resolved_at pair so the
 * resolution-duration aggregations are deterministic.
 */
function dashboardResolvedRequest(
    Organization $org,
    Customer $customer,
    User $requester,
    Carbon $createdAt,
    Carbon $resolvedAt,
    array $attributes = [],
): ServiceRequest {
    return ServiceRequest::factory()->create(array_merge([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'requested_by' => $requester->id,
        'status' => ServiceRequestStatus::Resolved,
        'created_at' => $createdAt,
        'resolved_at' => $resolvedAt,
        // The resolution-time aggregations only consider requests that carry a
        // resolution due date; give every helper-created one a due date.
        'resolution_due_at' => $resolvedAt->copy()->addHour(),
    ], $attributes));
}

it('requires dashboard.view for the dashboard endpoints', function () {
    $org = dashboardOrganization();
    $outsider = User::factory()->create(['organization_id' => $org->id]);
    $token = dashboardToken($outsider);

    foreach (['overview', 'request-volume', 'sla', 'team-performance'] as $endpoint) {
        $this->withToken($token)
            ->getJson("/api/v1/dashboard/{$endpoint}")
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }
});

it('requires report.view for team-performance', function () {
    $org = dashboardOrganization();
    $viewer = makeDashboardViewer($org);
    $token = dashboardToken($viewer);

    $this->withToken($token)
        ->getJson('/api/v1/dashboard/overview')
        ->assertOk();

    $this->withToken($token)
        ->getJson('/api/v1/dashboard/team-performance')
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FORBIDDEN');
});

it('returns every overview card and respects organization isolation', function () {
    $orgA = dashboardOrganization();
    $orgB = dashboardOrganization();
    $managerA = makeDashboardManager($orgA);
    $customerA = dashboardCustomer($orgA);
    $requesterA = User::factory()->create(['organization_id' => $orgA->id]);

    ServiceRequest::factory()->count(3)->create([
        'organization_id' => $orgA->id,
        'customer_id' => $customerA->id,
        'requested_by' => $requesterA->id,
    ]);

    dashboardResolvedRequest($orgA, $customerA, $requesterA, now()->subHours(3), now()->subHour());

    // Foreign organization noise must never leak into A's cards.
    $customerB = dashboardCustomer($orgB);
    $requesterB = User::factory()->create(['organization_id' => $orgB->id]);
    ServiceRequest::factory()->count(5)->create([
        'organization_id' => $orgB->id,
        'customer_id' => $customerB->id,
        'requested_by' => $requesterB->id,
    ]);

    $token = dashboardToken($managerA);

    $this->withToken($token)
        ->getJson('/api/v1/dashboard/overview')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'open_requests',
                'critical_open',
                'at_risk_sla',
                'breached_sla',
                'sla_compliance_percent',
                'resolved_today',
                'median_resolution_hours',
            ],
            'meta' => ['request_id'],
        ])
        ->assertJsonPath('data.open_requests', 3)
        ->assertJsonPath('data.sla_compliance_percent', 100)
        ->assertJsonPath('data.resolved_today', 1)
        ->assertJsonPath('data.median_resolution_hours', 2);
});

it('excludes resolved closed and cancelled requests from open_requests', function () {
    $org = dashboardOrganization();
    $manager = makeDashboardManager($org);
    $customer = dashboardCustomer($org);
    $requester = User::factory()->create(['organization_id' => $org->id]);

    ServiceRequest::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'requested_by' => $requester->id,
    ]);
    ServiceRequest::factory()->withStatus(ServiceRequestStatus::Resolved)->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'requested_by' => $requester->id,
    ]);
    ServiceRequest::factory()->withStatus(ServiceRequestStatus::Closed)->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'requested_by' => $requester->id,
    ]);
    ServiceRequest::factory()->withStatus(ServiceRequestStatus::Cancelled)->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'requested_by' => $requester->id,
    ]);

    $token = dashboardToken($manager);

    $this->withToken($token)
        ->getJson('/api/v1/dashboard/overview')
        ->assertOk()
        ->assertJsonPath('data.open_requests', 1)
        ->assertJsonPath('data.resolved_today', 1);
});

it('counts critical open and SLA at-risk and breached buckets', function () {
    $org = dashboardOrganization();
    $manager = makeDashboardManager($org);
    $customer = dashboardCustomer($org);
    $requester = User::factory()->create(['organization_id' => $org->id]);

    $create = function (array $attributes) use ($org, $customer, $requester): ServiceRequest {
        return ServiceRequest::factory()->create(array_merge([
            'organization_id' => $org->id,
            'customer_id' => $customer->id,
            'requested_by' => $requester->id,
        ], $attributes));
    };

    // Open, CRITICAL, resolution due within the next 4 hours → at-risk bucket.
    $create([
        'priority' => ServiceRequestPriority::Critical,
        'resolution_due_at' => now()->addHours(2),
    ]);
    // Open, due in 6 hours → neither at risk nor breached.
    $create([
        'priority' => ServiceRequestPriority::High,
        'resolution_due_at' => now()->addHours(6),
    ]);
    // Open, already past the due date → breached.
    $create([
        'resolution_due_at' => now()->subHour(),
    ]);
    // Terminal (RESOLVED) with a past due date → never breached.
    $create([
        'status' => ServiceRequestStatus::Resolved,
        'priority' => ServiceRequestPriority::Critical,
        'resolved_at' => now()->subHours(2),
        'resolution_due_at' => now()->subHour(),
    ]);

    $token = dashboardToken($manager);

    $this->withToken($token)
        ->getJson('/api/v1/dashboard/overview')
        ->assertOk()
        ->assertJsonPath('data.open_requests', 3)
        ->assertJsonPath('data.critical_open', 1)
        ->assertJsonPath('data.at_risk_sla', 1)
        ->assertJsonPath('data.breached_sla', 1);
});

it('computes the SLA compliance percentage from resolved requests', function () {
    $org = dashboardOrganization();
    $manager = makeDashboardManager($org);
    $customer = dashboardCustomer($org);
    $requester = User::factory()->create(['organization_id' => $org->id]);

    // One met (resolved before its due date) and two breached (resolved
    // after their due date) → 33% compliance.
    dashboardResolvedRequest($org, $customer, $requester, now()->subHours(6), now()->subHours(5), [
        'resolution_due_at' => now()->subHours(4),
    ]);
    dashboardResolvedRequest($org, $customer, $requester, now()->subHours(8), now()->subHours(3), [
        'resolution_due_at' => now()->subHours(4),
    ]);
    dashboardResolvedRequest($org, $customer, $requester, now()->subHours(3), now()->subMinutes(30), [
        'resolution_due_at' => now()->subHour(),
    ]);

    $token = dashboardToken($manager);

    $this->withToken($token)
        ->getJson('/api/v1/dashboard/overview')
        ->assertOk()
        ->assertJsonPath('data.sla_compliance_percent', 33);
});

it('defaults the SLA compliance to 100 when there are no resolved requests', function () {
    $org = dashboardOrganization();
    $manager = makeDashboardManager($org);
    $customer = dashboardCustomer($org);
    $requester = User::factory()->create(['organization_id' => $org->id]);

    // Open and cancelled requests carry a due date but none are resolved.
    ServiceRequest::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'requested_by' => $requester->id,
        'resolution_due_at' => now()->addDay(),
    ]);
    ServiceRequest::factory()->withStatus(ServiceRequestStatus::Cancelled)->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'requested_by' => $requester->id,
        'resolution_due_at' => now()->addDay(),
    ]);

    $token = dashboardToken($manager);

    $this->withToken($token)
        ->getJson('/api/v1/dashboard/overview')
        ->assertOk()
        ->assertJsonPath('data.sla_compliance_percent', 100)
        ->assertJsonPath('data.median_resolution_hours', null);
});

it('reports request volume over the last 30 days with status and priority counts', function () {
    $org = dashboardOrganization();
    $manager = makeDashboardManager($org);
    $customer = dashboardCustomer($org);
    $requester = User::factory()->create(['organization_id' => $org->id]);

    ServiceRequest::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'requested_by' => $requester->id,
        'priority' => ServiceRequestPriority::Low,
        'status' => ServiceRequestStatus::InProgress,
        'created_at' => now()->startOfDay()->addHours(9),
    ]);
    ServiceRequest::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'requested_by' => $requester->id,
        'priority' => ServiceRequestPriority::Critical,
        'status' => ServiceRequestStatus::New,
        'created_at' => now()->startOfDay()->addHours(9),
    ]);
    ServiceRequest::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'requested_by' => $requester->id,
        'priority' => ServiceRequestPriority::Critical,
        'status' => ServiceRequestStatus::Resolved,
        'created_at' => now()->subDays(3)->startOfDay(),
        'resolved_at' => now()->subDays(3)->startOfDay()->addHours(2),
    ]);

    $token = dashboardToken($manager);

    $response = $this->withToken($token)
        ->getJson('/api/v1/dashboard/request-volume')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'daily' => [['date', 'count']],
                'status_counts' => [['status', 'count']],
                'priority_counts' => [['priority', 'count']],
            ],
        ])
        ->assertJsonCount(30, 'data.daily')
        ->assertJsonPath('data.daily.29.date', now()->toDateString())
        ->assertJsonPath('data.daily.29.count', 2)
        ->assertJsonPath('data.daily.26.date', now()->subDays(3)->toDateString())
        ->assertJsonPath('data.daily.26.count', 1);

    $statuses = collect($response->json('data.status_counts'))->keyBy('status');
    $priorities = collect($response->json('data.priority_counts'))->keyBy('priority');

    expect($statuses->get('IN_PROGRESS')['count'])->toBe(1);
    expect($statuses->get('NEW')['count'])->toBe(1);
    expect($statuses->get('RESOLVED')['count'])->toBe(1);
    expect($priorities->get('LOW')['count'])->toBe(1);
    expect($priorities->get('CRITICAL')['count'])->toBe(2);
});

it('isolates request volume per organization', function () {
    $orgA = dashboardOrganization();
    $orgB = dashboardOrganization();
    $managerA = makeDashboardManager($orgA);
    $customerA = dashboardCustomer($orgA);
    $requesterA = User::factory()->create(['organization_id' => $orgA->id]);
    $customerB = dashboardCustomer($orgB);
    $requesterB = User::factory()->create(['organization_id' => $orgB->id]);

    ServiceRequest::factory()->count(2)->create([
        'organization_id' => $orgA->id,
        'customer_id' => $customerA->id,
        'requested_by' => $requesterA->id,
        'created_at' => now()->startOfDay(),
    ]);
    ServiceRequest::factory()->count(7)->create([
        'organization_id' => $orgB->id,
        'customer_id' => $customerB->id,
        'requested_by' => $requesterB->id,
        'created_at' => now()->startOfDay(),
    ]);

    $token = dashboardToken($managerA);

    $this->withToken($token)
        ->getJson('/api/v1/dashboard/request-volume')
        ->assertOk()
        ->assertJsonPath('data.daily.29.count', 2);
});

it('returns the 30 day SLA trend with met counts and overall compliance', function () {
    $org = dashboardOrganization();
    $manager = makeDashboardManager($org);
    $customer = dashboardCustomer($org);
    $requester = User::factory()->create(['organization_id' => $org->id]);

    // 2 resolved yesterday, one on time and one late.
    dashboardResolvedRequest($org, $customer, $requester, now()->subDays(2), now()->subDays(1)->startOfDay()->addHour(), [
        'resolution_due_at' => now()->subDays(1)->startOfDay()->addHours(3),
    ]);
    dashboardResolvedRequest($org, $customer, $requester, now()->subDays(2), now()->subDays(1)->startOfDay()->addHours(2), [
        'resolution_due_at' => now()->subDays(1)->startOfDay()->addHour(),
    ]);

    $token = dashboardToken($manager);

    $this->withToken($token)
        ->getJson('/api/v1/dashboard/sla')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'trend' => [['date', 'resolved', 'met']],
                'overall_compliance_percent',
            ],
        ])
        ->assertJsonCount(30, 'data.trend')
        ->assertJsonPath('data.trend.29.date', now()->toDateString())
        ->assertJsonPath('data.trend.29.resolved', 0)
        ->assertJsonPath('data.trend.29.met', 0)
        ->assertJsonPath('data.trend.28.date', now()->subDay()->toDateString())
        ->assertJsonPath('data.trend.28.resolved', 2)
        ->assertJsonPath('data.trend.28.met', 1)
        ->assertJsonPath('data.overall_compliance_percent', 50);
});

it('aggregates team performance by assignee with open and resolved counts', function () {
    $org = dashboardOrganization();
    $manager = makeDashboardManager($org);
    $customer = dashboardCustomer($org);
    $requester = User::factory()->create(['organization_id' => $org->id]);
    $alice = User::factory()->create(['organization_id' => $org->id, 'name' => 'Alice Op']);
    $bob = User::factory()->create(['organization_id' => $org->id, 'name' => 'Bob Op']);

    // Alice: one open, one resolved (3h).
    ServiceRequest::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'requested_by' => $requester->id,
        'assigned_to' => $alice->id,
        'status' => ServiceRequestStatus::InProgress,
    ]);
    dashboardResolvedRequest($org, $customer, $requester, now()->subHours(4), now()->subHour(), [
        'assigned_to' => $alice->id,
    ]);
    // Bob: one open (no timestamps).
    ServiceRequest::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'requested_by' => $requester->id,
        'assigned_to' => $bob->id,
    ]);
    // A resolved request without an assignee is never attributed.
    dashboardResolvedRequest($org, $customer, $requester, now()->subHours(2), now()->subHour());

    $token = dashboardToken($manager);

    $this->withToken($token)
        ->getJson('/api/v1/dashboard/team-performance')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [[
                'user_id',
                'name',
                'open_count',
                'resolved_count',
                'avg_resolution_hours',
                'avg_first_response_hours',
            ]],
            'meta' => ['request_id'],
        ])
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.user_id', $alice->id)
        ->assertJsonPath('data.0.name', 'Alice Op')
        ->assertJsonPath('data.0.open_count', 1)
        ->assertJsonPath('data.0.resolved_count', 1)
        ->assertJsonPath('data.0.avg_resolution_hours', 3)
        ->assertJsonPath('data.0.avg_first_response_hours', null)
        ->assertJsonPath('data.1.user_id', $bob->id)
        ->assertJsonPath('data.1.name', 'Bob Op')
        ->assertJsonPath('data.1.open_count', 1)
        ->assertJsonPath('data.1.resolved_count', 0)
        ->assertJsonPath('data.1.avg_resolution_hours', null);
});

it('returns an empty team list when nothing is assigned and scopes it per organization', function () {
    $orgA = dashboardOrganization();
    $orgB = dashboardOrganization();
    $managerA = makeDashboardManager($orgA);
    $customerB = dashboardCustomer($orgB);
    $requesterB = User::factory()->create(['organization_id' => $orgB->id]);
    $assigneeB = User::factory()->create(['organization_id' => $orgB->id]);

    ServiceRequest::factory()->create([
        'organization_id' => $orgB->id,
        'customer_id' => $customerB->id,
        'requested_by' => $requesterB->id,
        'assigned_to' => $assigneeB->id,
    ]);

    $token = dashboardToken($managerA);

    $this->withToken($token)
        ->getJson('/api/v1/dashboard/team-performance')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('caches dashboard payloads per organization key', function () {
    $org = dashboardOrganization();
    $manager = makeDashboardManager($org);
    $token = dashboardToken($manager);

    $first = $this->withToken($token)->getJson('/api/v1/dashboard/overview')->assertOk();
    $cached = $this->withToken($token)->getJson('/api/v1/dashboard/overview')->assertOk();

    expect($first->json('data'))->toBe($cached->json('data'));
    expect(Cache::has(DashboardCache::key(DashboardCache::OVERVIEW, $org->id)))->toBeTrue();

    // A different organization uses a different cache key.
    $orgB = dashboardOrganization();
    expect(Cache::has(DashboardCache::key(DashboardCache::OVERVIEW, $orgB->id)))->toBeFalse();
});
