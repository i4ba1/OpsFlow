<?php

use App\Enums\ServiceRequestEventType;
use App\Enums\ServiceRequestStatus;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ServiceRequest;
use App\Models\User;

/**
 * Grant the given user a single permission by attaching it through a fresh
 * role, mirroring how permission middleware checks real DB rows.
 */
function grantServiceRequestWorkflowPermission(User $user, string $permission): void
{
    $permissionModel = Permission::firstOrCreate(
        ['name' => $permission],
        ['group' => explode('.', $permission)[0], 'description' => ucfirst($permission)],
    );

    $role = Role::factory()->create(['organization_id' => $user->organization_id]);
    $role->permissions()->attach($permissionModel->name);

    $user->roles()->attach($role->id);
}

/**
 * A user with the full request permission set plus the dedicated workflow
 * capabilities, ready to drive a request end to end.
 */
function makeWorkflowOperator(Organization $org): User
{
    $user = User::factory()->create(['organization_id' => $org->id]);

    foreach (['request.view', 'request.create', 'request.transition', 'request.resolve', 'request.close', 'request.reopen', 'request.assign', 'request.change_priority'] as $permission) {
        grantServiceRequestWorkflowPermission($user, $permission);
    }

    return $user;
}

function workflowRequest(Organization $org, User $requester, Customer $customer): ServiceRequest
{
    return ServiceRequest::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'requested_by' => $requester->id,
    ]);
}

it('rejects an illegal NEW to IN_PROGRESS transition', function () {
    $org = Organization::factory()->create();
    $admin = makeWorkflowOperator($org);
    $customer = Customer::factory()->create(['organization_id' => $org->id]);
    $request = workflowRequest($org, $admin, $customer);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/service-requests/{$request->id}/transition", [
            'status' => 'IN_PROGRESS',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'REQUEST_INVALID_TRANSITION');

    expect($request->fresh()->status)->toBe(ServiceRequestStatus::New);
});

it('walks the happy path NEW to TRIAGED to IN_PROGRESS', function () {
    $org = Organization::factory()->create();
    $admin = makeWorkflowOperator($org);
    $customer = Customer::factory()->create(['organization_id' => $org->id]);
    $request = workflowRequest($org, $admin, $customer);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/service-requests/{$request->id}/transition", ['status' => 'TRIAGED'])
        ->assertOk()
        ->assertJsonPath('data.status', 'TRIAGED')
        ->assertJsonPath('data.version', 2);

    $this->withToken($token)
        ->postJson("/api/v1/service-requests/{$request->id}/transition", ['status' => 'IN_PROGRESS'])
        ->assertOk()
        ->assertJsonPath('data.status', 'IN_PROGRESS')
        ->assertJsonPath('data.version', 3);

    $fresh = $request->fresh();

    expect($fresh->status)->toBe(ServiceRequestStatus::InProgress);
    expect($fresh->first_responded_at)->not->toBeNull();
});

it('handles the waiting customer round trip', function () {
    $org = Organization::factory()->create();
    $admin = makeWorkflowOperator($org);
    $customer = Customer::factory()->create(['organization_id' => $org->id]);
    $request = workflowRequest($org, $admin, $customer);

    $token = $admin->createToken('spa')->plainTextToken;

    foreach (['TRIAGED', 'IN_PROGRESS', 'WAITING_CUSTOMER'] as $status) {
        $this->withToken($token)
            ->postJson("/api/v1/service-requests/{$request->id}/transition", ['status' => $status])
            ->assertOk()
            ->assertJsonPath('data.status', $status);
    }

    $this->withToken($token)
        ->postJson("/api/v1/service-requests/{$request->id}/transition", ['status' => 'IN_PROGRESS'])
        ->assertOk()
        ->assertJsonPath('data.status', 'IN_PROGRESS');

    expect($request->fresh()->status)->toBe(ServiceRequestStatus::InProgress);
});

it('sets resolved_at when transitioning to RESOLVED', function () {
    $org = Organization::factory()->create();
    $admin = makeWorkflowOperator($org);
    $customer = Customer::factory()->create(['organization_id' => $org->id]);
    $request = workflowRequest($org, $admin, $customer);
    $request->update(['status' => ServiceRequestStatus::InProgress, 'first_responded_at' => now()]);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/service-requests/{$request->id}/transition", ['status' => 'RESOLVED'])
        ->assertOk()
        ->assertJsonPath('data.status', 'RESOLVED');

    $fresh = $request->fresh();

    expect($fresh->status)->toBe(ServiceRequestStatus::Resolved);
    expect($fresh->resolved_at)->not->toBeNull();
    expect($fresh->closed_at)->toBeNull();
});

it('sets closed_at when a resolved request is closed', function () {
    $org = Organization::factory()->create();
    $admin = makeWorkflowOperator($org);
    $customer = Customer::factory()->create(['organization_id' => $org->id]);
    $request = workflowRequest($org, $admin, $customer);
    $request->update([
        'status' => ServiceRequestStatus::Resolved,
        'resolved_at' => now(),
    ]);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/service-requests/{$request->id}/transition", ['status' => 'CLOSED'])
        ->assertOk()
        ->assertJsonPath('data.status', 'CLOSED');

    $fresh = $request->fresh();

    expect($fresh->status)->toBe(ServiceRequestStatus::Closed);
    expect($fresh->resolved_at)->not->toBeNull();
    expect($fresh->closed_at)->not->toBeNull();
});

it('reopens a resolved request and clears resolved_at', function () {
    $org = Organization::factory()->create();
    $admin = makeWorkflowOperator($org);
    $customer = Customer::factory()->create(['organization_id' => $org->id]);
    $request = workflowRequest($org, $admin, $customer);
    $request->update([
        'status' => ServiceRequestStatus::Resolved,
        'resolved_at' => now()->subDay(),
    ]);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/service-requests/{$request->id}/transition", ['status' => 'IN_PROGRESS'])
        ->assertOk()
        ->assertJsonPath('data.status', 'IN_PROGRESS');

    $fresh = $request->fresh();

    expect($fresh->status)->toBe(ServiceRequestStatus::InProgress);
    expect($fresh->resolved_at)->toBeNull();
    expect($fresh->closed_at)->toBeNull();
});

it('allows a manager-style reopen from CLOSED', function () {
    $org = Organization::factory()->create();
    $admin = makeWorkflowOperator($org);
    $customer = Customer::factory()->create(['organization_id' => $org->id]);
    $request = workflowRequest($org, $admin, $customer);
    $request->update([
        'status' => ServiceRequestStatus::Closed,
        'resolved_at' => now()->subDay(),
        'closed_at' => now()->subDay(),
    ]);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/service-requests/{$request->id}/transition", ['status' => 'IN_PROGRESS'])
        ->assertOk()
        ->assertJsonPath('data.status', 'IN_PROGRESS');

    expect($request->fresh()->status)->toBe(ServiceRequestStatus::InProgress);
});

it('rejects a transition from CANCELLED', function () {
    $org = Organization::factory()->create();
    $admin = makeWorkflowOperator($org);
    $customer = Customer::factory()->create(['organization_id' => $org->id]);
    $request = workflowRequest($org, $admin, $customer);
    $request->update(['status' => ServiceRequestStatus::Cancelled]);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/service-requests/{$request->id}/transition", ['status' => 'TRIAGED'])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'REQUEST_INVALID_TRANSITION');
});

it('requires the request.transition permission for generic transitions', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $org->id]);
    grantServiceRequestWorkflowPermission($user, 'request.view');
    grantServiceRequestWorkflowPermission($user, 'request.create');

    $customer = Customer::factory()->create(['organization_id' => $org->id]);
    $request = workflowRequest($org, $user, $customer);

    $token = $user->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/service-requests/{$request->id}/transition", ['status' => 'TRIAGED'])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FORBIDDEN');

    expect($request->fresh()->status)->toBe(ServiceRequestStatus::New);
});

it('requires request.resolve to resolve and request.reopen to reopen', function () {
    $org = Organization::factory()->create();
    $operator = User::factory()->create(['organization_id' => $org->id]);
    grantServiceRequestWorkflowPermission($operator, 'request.view');
    grantServiceRequestWorkflowPermission($operator, 'request.transition');

    $customer = Customer::factory()->create(['organization_id' => $org->id]);
    $request = workflowRequest($org, $operator, $customer);
    $request->update([
        'status' => ServiceRequestStatus::InProgress,
        'first_responded_at' => now(),
    ]);

    $token = $operator->createToken('spa')->plainTextToken;

    // request.transition alone is not enough for the resolve edge.
    $this->withToken($token)
        ->postJson("/api/v1/service-requests/{$request->id}/transition", ['status' => 'RESOLVED'])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FORBIDDEN');

    grantServiceRequestWorkflowPermission($operator, 'request.resolve');

    $this->withToken($token)
        ->postJson("/api/v1/service-requests/{$request->id}/transition", ['status' => 'RESOLVED'])
        ->assertOk()
        ->assertJsonPath('data.status', 'RESOLVED');

    // A plain transitioner cannot reopen — the reopen edge needs request.reopen.
    $this->withToken($token)
        ->postJson("/api/v1/service-requests/{$request->id}/transition", ['status' => 'IN_PROGRESS'])
        ->assertForbidden();

    grantServiceRequestWorkflowPermission($operator, 'request.reopen');

    $this->withToken($token)
        ->postJson("/api/v1/service-requests/{$request->id}/transition", ['status' => 'IN_PROGRESS'])
        ->assertOk()
        ->assertJsonPath('data.status', 'IN_PROGRESS');
});

it('records a timeline event row for every transition', function () {
    $org = Organization::factory()->create();
    $admin = makeWorkflowOperator($org);
    $customer = Customer::factory()->create(['organization_id' => $org->id]);
    $request = workflowRequest($org, $admin, $customer);

    $token = $admin->createToken('spa')->plainTextToken;

    foreach (['TRIAGED', 'IN_PROGRESS', 'WAITING_CUSTOMER', 'IN_PROGRESS', 'RESOLVED'] as $status) {
        $this->withToken($token)
            ->postJson("/api/v1/service-requests/{$request->id}/transition", ['status' => $status])
            ->assertOk();
    }

    $fresh = $request->fresh();
    $events = $fresh->events()->orderBy('created_at')->get();

    expect($events->count())->toBe(5); // one row per transition
    expect($events->pluck('type')->all())->each->toBe(ServiceRequestEventType::StatusChanged);
    expect($events->last()->from_value)->toBe('IN_PROGRESS');
    expect($events->last()->to_value)->toBe('RESOLVED');
});

it('increments the version on every transition and conflicts on stale version', function () {
    $org = Organization::factory()->create();
    $admin = makeWorkflowOperator($org);
    $customer = Customer::factory()->create(['organization_id' => $org->id]);
    $request = workflowRequest($org, $admin, $customer);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/service-requests/{$request->id}/transition", [
            'status' => 'TRIAGED',
            'version' => 1,
        ])
        ->assertOk()
        ->assertJsonPath('data.version', 2);

    // The client still holds version 1, so the second transition conflicts.
    $this->withToken($token)
        ->postJson("/api/v1/service-requests/{$request->id}/transition", [
            'status' => 'IN_PROGRESS',
            'version' => 1,
        ])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'RESOURCE_VERSION_CONFLICT');

    expect($request->fresh()->status)->toBe(ServiceRequestStatus::Triaged);
});

it('serves the unified history endpoint with actor details', function () {
    $org = Organization::factory()->create();
    $admin = makeWorkflowOperator($org);
    $customer = Customer::factory()->create(['organization_id' => $org->id]);

    $token = $admin->createToken('spa')->plainTextToken;

    // Created through the API so the timeline starts with the NEW creation row.
    $this->withToken($token)
        ->postJson('/api/v1/service-requests', [
            'customer_id' => $customer->id,
            'title' => 'History target',
            'description' => 'Request with an auditable timeline.',
        ])
        ->assertCreated();

    $request = ServiceRequest::query()->firstOrFail();

    $this->withToken($token)
        ->postJson("/api/v1/service-requests/{$request->id}/transition", [
            'status' => 'TRIAGED',
            'note' => 'Triaged by the operator',
        ])
        ->assertOk();

    $this->withToken($token)
        ->getJson("/api/v1/service-requests/{$request->id}/history")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.type', 'STATUS_CHANGED')
        ->assertJsonPath('data.0.data.from', null)
        ->assertJsonPath('data.0.data.to', 'NEW')
        ->assertJsonPath('data.1.type', 'STATUS_CHANGED')
        ->assertJsonPath('data.1.data.from', 'NEW')
        ->assertJsonPath('data.1.data.to', 'TRIAGED')
        ->assertJsonPath('data.1.note', 'Triaged by the operator')
        ->assertJsonPath('data.1.actor.id', $admin->id)
        ->assertJsonPath('data.1.actor.name', $admin->name);
});

it('assigns a request and records the assignment on the timeline', function () {
    $org = Organization::factory()->create();
    $admin = makeWorkflowOperator($org);
    $customer = Customer::factory()->create(['organization_id' => $org->id]);
    $request = workflowRequest($org, $admin, $customer);
    $assignee = User::factory()->create(['organization_id' => $org->id]);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/service-requests/{$request->id}/assign", [
            'assigned_to' => $assignee->id,
            'note' => 'Take this one',
        ])
        ->assertOk()
        ->assertJsonPath('data.assignee.id', $assignee->id)
        ->assertJsonPath('data.version', 2);

    $fresh = $request->fresh();

    expect($fresh->assigned_to)->toBe($assignee->id);

    $assignmentEvent = $fresh->events()->where('type', 'ASSIGNED')->first();

    expect($assignmentEvent)->not->toBeNull();
    expect($assignmentEvent->to_value)->toBe($assignee->id);
    expect($assignmentEvent->actor_id)->toBe($admin->id);
});

it('rejects assigning a request in a terminal state', function () {
    $org = Organization::factory()->create();
    $admin = makeWorkflowOperator($org);
    $customer = Customer::factory()->create(['organization_id' => $org->id]);
    $request = workflowRequest($org, $admin, $customer);
    $request->update([
        'status' => ServiceRequestStatus::Closed,
        'resolved_at' => now(),
        'closed_at' => now(),
    ]);
    $assignee = User::factory()->create(['organization_id' => $org->id]);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/service-requests/{$request->id}/assign", [
            'assigned_to' => $assignee->id,
        ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'REQUEST_NOT_ASSIGNABLE');

    expect($request->fresh()->assigned_to)->toBeNull();
});

it('changes the priority and records it on the timeline', function () {
    $org = Organization::factory()->create();
    $admin = makeWorkflowOperator($org);
    $customer = Customer::factory()->create(['organization_id' => $org->id]);
    $request = workflowRequest($org, $admin, $customer);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/service-requests/{$request->id}/priority", ['priority' => 'CRITICAL'])
        ->assertOk()
        ->assertJsonPath('data.priority', 'CRITICAL')
        ->assertJsonPath('data.version', 2);

    $fresh = $request->fresh();

    expect($fresh->priority->value)->toBe('CRITICAL');

    $event = $fresh->events()->where('type', 'PRIORITY_CHANGED')->first();

    expect($event)->not->toBeNull();
    expect($event->from_value)->toBe('MEDIUM');
    expect($event->to_value)->toBe('CRITICAL');
});

it('refuses a history request for a foreign request', function () {
    $orgA = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $orgA->id]);
    grantServiceRequestWorkflowPermission($admin, 'request.view');

    $orgB = Organization::factory()->create();
    $customerB = Customer::factory()->create(['organization_id' => $orgB->id]);
    $foreignUser = User::factory()->create(['organization_id' => $orgB->id]);
    $foreign = ServiceRequest::factory()->create([
        'organization_id' => $orgB->id,
        'customer_id' => $customerB->id,
        'requested_by' => $foreignUser->id,
    ]);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->getJson("/api/v1/service-requests/{$foreign->id}/history")
        ->assertNotFound()
        ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
});

it('exposes the transitions the current user may currently perform', function () {
    $org = Organization::factory()->create();
    $admin = makeWorkflowOperator($org);
    $customer = Customer::factory()->create(['organization_id' => $org->id]);
    $request = workflowRequest($org, $admin, $customer);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->getJson("/api/v1/service-requests/{$request->id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'NEW')
        ->assertJsonFragment(['status' => 'TRIAGED', 'permission' => 'request.transition'])
        ->assertJsonFragment(['status' => 'CANCELLED', 'permission' => 'request.close']);
});

it('hides transitions the user lacks permission to perform', function () {
    $org = Organization::factory()->create();
    $viewer = User::factory()->create(['organization_id' => $org->id]);
    grantServiceRequestWorkflowPermission($viewer, 'request.view');

    $customer = Customer::factory()->create(['organization_id' => $org->id]);
    $request = workflowRequest($org, $viewer, $customer);

    $token = $viewer->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->getJson("/api/v1/service-requests/{$request->id}")
        ->assertOk()
        ->assertJsonPath('data.available_transitions', []);
});
