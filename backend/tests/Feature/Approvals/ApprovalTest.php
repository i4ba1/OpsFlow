<?php

use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Jobs\ExpirePendingApprovals;
use App\Models\Approval;
use App\Models\Customer;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Support\Notifications\NotificationType;

/**
 * Grant the given user a single permission by attaching it through a fresh
 * role, mirroring how permission middleware checks real DB rows.
 */
function grantApprovalPermission(User $user, string $permission): void
{
    $permissionModel = Permission::firstOrCreate(
        ['name' => $permission],
        ['group' => explode('.', $permission)[0], 'description' => ucfirst($permission)],
    );

    $role = Role::query()->firstOrCreate(
        ['organization_id' => $user->organization_id, 'slug' => 'approver-'.$permission],
        ['name' => 'Approver '.$permission, 'description' => null],
    );
    $role->permissions()->syncWithoutDetaching($permissionModel->name);
    $user->roles()->syncWithoutDetaching($role->id);
}

function makeApprovalManager(Organization $org): User
{
    $user = User::factory()->create(['organization_id' => $org->id]);

    foreach (['request.view', 'approval.view', 'approval.request', 'approval.respond', 'approval.cancel'] as $permission) {
        grantApprovalPermission($user, $permission);
    }

    return $user;
}

function makeApprovalAssignee(Organization $org): User
{
    $user = User::factory()->create(['organization_id' => $org->id]);

    foreach (['request.view', 'approval.view', 'approval.respond'] as $permission) {
        grantApprovalPermission($user, $permission);
    }

    return $user;
}

function approvalRequest(Organization $org, User $requester): ServiceRequest
{
    $customer = Customer::factory()->create(['organization_id' => $org->id]);

    return ServiceRequest::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'requested_by' => $requester->id,
    ]);
}

/**
 * Seed an approval on a request for the given org; the requester and assignee
 * are real org users.
 */
function seededApproval(Organization $org, ServiceRequest $request, User $requester, User $assignee, array $attributes = []): Approval
{
    return Approval::factory()->create(array_merge([
        'organization_id' => $org->id,
        'service_request_id' => $request->id,
        'requested_by' => $requester->id,
        'assigned_to' => $assignee->id,
        'type' => ApprovalType::Acceptance,
        'title' => 'Approve the quotation',
        'status' => ApprovalStatus::Pending,
    ], $attributes));
}

it('creates a PENDING approval assigned to an org user', function () {
    $org = Organization::factory()->create();
    $manager = makeApprovalManager($org);
    $assignee = makeApprovalAssignee($org);
    $request = approvalRequest($org, $manager);

    $token = $manager->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/service-requests/{$request->id}/approvals", [
            'assigned_to' => $assignee->id,
            'type' => 'ACCEPTANCE',
            'title' => 'Approve UAT sign-off',
            'description' => 'Customer needs to accept the delivered milestone.',
            'due_at' => '2026-09-30T23:59:59+08:00',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'PENDING')
        ->assertJsonPath('data.type', 'ACCEPTANCE')
        ->assertJsonPath('data.assignee.id', $assignee->id)
        ->assertJsonPath('data.requested_by.id', $manager->id);

    $approval = Approval::query()->firstOrFail();

    expect($approval->organization_id)->toBe($org->id);
    expect($approval->service_request_id)->toBe($request->id);
    expect($approval->requested_by)->toBe($manager->id);
    expect($approval->assigned_to)->toBe($assignee->id);
    expect($approval->status)->toBe(ApprovalStatus::Pending);
    expect($approval->type)->toBe(ApprovalType::Acceptance);
    expect($approval->due_at)->not->toBeNull();
    expect($approval->responded_at)->toBeNull();

    // An APPROVAL_REQUESTED timeline row is recorded on the request.
    $event = $request->events()->where('type', 'APPROVAL_REQUESTED')->first();

    expect($event)->not->toBeNull();
    expect($event->actor_id)->toBe($manager->id);
    expect($event->to_value)->toBe($approval->id);
});

it('rejects assigning an approval to a user outside the organization', function () {
    $org = Organization::factory()->create();
    $manager = makeApprovalManager($org);
    $request = approvalRequest($org, $manager);

    $foreignOrg = Organization::factory()->create();
    $foreign = User::factory()->create(['organization_id' => $foreignOrg->id]);

    $token = $manager->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/service-requests/{$request->id}/approvals", [
            'assigned_to' => $foreign->id,
            'type' => 'EXCEPTION',
            'title' => 'Foreign assignee',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');

    expect(Approval::query()->count())->toBe(0);
});

it('requires approval.request to create an approval', function () {
    $org = Organization::factory()->create();
    $assignee = makeApprovalAssignee($org);
    $viewer = User::factory()->create(['organization_id' => $org->id]);
    grantApprovalPermission($viewer, 'request.view');
    $request = approvalRequest($org, $viewer);

    $token = $viewer->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/service-requests/{$request->id}/approvals", [
            'assigned_to' => $assignee->id,
            'type' => 'QUOTATION',
            'title' => 'Not allowed',
        ])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FORBIDDEN');

    expect(Approval::query()->count())->toBe(0);
});

it('lists approvals paginated with status and assigned_to=me filters', function () {
    $org = Organization::factory()->create();
    $manager = makeApprovalManager($org);
    $request = approvalRequest($org, $manager);

    seededApproval($org, $request, $manager, $manager, ['status' => ApprovalStatus::Pending, 'title' => 'Mine pending']);
    seededApproval($org, $request, $manager, $manager, [
        'status' => ApprovalStatus::Approved,
        'responded_at' => now(),
        'title' => 'Mine approved',
    ]);
    $otherAssignee = makeApprovalAssignee($org);
    seededApproval($org, $request, $manager, $otherAssignee, ['status' => ApprovalStatus::Pending, 'title' => 'Someone else']);

    $token = $manager->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/approvals?status[]=PENDING&status[]=APPROVED&assigned_to=me')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 2);

    $this->withToken($token)
        ->getJson('/api/v1/approvals?status[]=PENDING')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 2);

    // The other assignee only sees their own row with assigned_to=me.
    $otherToken = $otherAssignee->createToken('spa-other')->plainTextToken;

    auth()->forgetGuards();

    $this->withToken($otherToken)
        ->getJson('/api/v1/approvals?assigned_to=me')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Someone else');
});

it('lists approvals sorted by due date and filters by service request', function () {
    $org = Organization::factory()->create();
    $manager = makeApprovalManager($org);
    $assignee = makeApprovalAssignee($org);
    $request = approvalRequest($org, $manager);

    $first = seededApproval($org, $request, $manager, $assignee, [
        'title' => 'Due later',
        'due_at' => now()->addDays(5),
        'created_at' => now()->subMinutes(2),
        'updated_at' => now()->subMinutes(2),
    ]);
    $second = seededApproval($org, $request, $manager, $assignee, [
        'title' => 'Due sooner',
        'due_at' => now()->addDay(),
    ]);

    $token = $manager->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/approvals?sort=due_at&direction=asc&service_request_id='.$request->id)
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $second->id)
        ->assertJsonPath('data.1.id', $first->id);

    // Default ordering is -created_at, newest first.
    $this->withToken($token)
        ->getJson('/api/v1/approvals')
        ->assertOk()
        ->assertJsonPath('data.0.id', $second->id);
});

it('shows an approval detail with relations', function () {
    $org = Organization::factory()->create();
    $manager = makeApprovalManager($org);
    $assignee = makeApprovalAssignee($org);
    $request = approvalRequest($org, $manager);
    $approval = seededApproval($org, $request, $manager, $assignee, ['title' => 'Detail target']);

    $token = $manager->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->getJson("/api/v1/approvals/{$approval->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $approval->id)
        ->assertJsonPath('data.title', 'Detail target')
        ->assertJsonPath('data.service_request.id', $request->id)
        ->assertJsonPath('data.assignee.id', $assignee->id);
});

it('lets the assignee approve and records the response', function () {
    $org = Organization::factory()->create();
    $manager = makeApprovalManager($org);
    $assignee = makeApprovalAssignee($org);
    $request = approvalRequest($org, $manager);
    $approval = seededApproval($org, $request, $manager, $assignee, ['title' => 'Approve me']);

    $token = $assignee->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/approvals/{$approval->id}/approve", [
            'response_note' => 'Looks good, ship it.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'APPROVED');

    $fresh = $approval->fresh();

    expect($fresh->status)->toBe(ApprovalStatus::Approved);
    expect($fresh->responded_at)->not->toBeNull();
    expect($fresh->response_note)->toBe('Looks good, ship it.');

    // The response lands on the unified timeline.
    $event = $request->events()->where('type', 'APPROVAL_RESPONDED')->first();

    expect($event)->not->toBeNull();
    expect($event->actor_id)->toBe($assignee->id);
    expect($event->to_value)->toBe('APPROVED');
    expect($event->note)->toBe('Looks good, ship it.');
});

it('forbids a non-assignee from responding', function () {
    $org = Organization::factory()->create();
    $manager = makeApprovalManager($org);
    $assignee = makeApprovalAssignee($org);
    $intruder = makeApprovalAssignee($org);
    $request = approvalRequest($org, $manager);
    $approval = seededApproval($org, $request, $manager, $assignee);

    $token = $intruder->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/approvals/{$approval->id}/approve")
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FORBIDDEN');

    expect($approval->fresh()->status)->toBe(ApprovalStatus::Pending);
});

it('returns 404 when responding to a foreign-organization approval', function () {
    $orgA = Organization::factory()->create();
    $managerA = makeApprovalManager($orgA);
    $assigneeA = makeApprovalAssignee($orgA);
    $requestA = approvalRequest($orgA, $managerA);

    $orgB = Organization::factory()->create();
    $managerB = makeApprovalManager($orgB);
    $requestB = approvalRequest($orgB, $managerB);
    $foreignApproval = seededApproval($orgB, $requestB, $managerB, $managerB);

    $token = $assigneeA->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/approvals/{$foreignApproval->id}/approve")
        ->assertNotFound()
        ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');

    expect($foreignApproval->fresh()->status)->toBe(ApprovalStatus::Pending);
});

it('rejects a second response with APPROVAL_ALREADY_RESPONDED', function () {
    $org = Organization::factory()->create();
    $manager = makeApprovalManager($org);
    $assignee = makeApprovalAssignee($org);
    $request = approvalRequest($org, $manager);
    $approval = seededApproval($org, $request, $manager, $assignee);

    $token = $assignee->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/approvals/{$approval->id}/approve")
        ->assertOk();

    $this->withToken($token)
        ->postJson("/api/v1/approvals/{$approval->id}/approve")
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'APPROVAL_ALREADY_RESPONDED');

    expect(Approval::query()->where('status', ApprovalStatus::Approved)->count())->toBe(1);
});

it('rejects an approval with a response note', function () {
    $org = Organization::factory()->create();
    $manager = makeApprovalManager($org);
    $assignee = makeApprovalAssignee($org);
    $request = approvalRequest($org, $manager);
    $approval = seededApproval($org, $request, $manager, $assignee);

    $token = $assignee->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/approvals/{$approval->id}/reject", [
            'response_note' => 'Quotation exceeds budget.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'REJECTED')
        ->assertJsonPath('data.response_note', 'Quotation exceeds budget.');

    $fresh = $approval->fresh();

    expect($fresh->status)->toBe(ApprovalStatus::Rejected);
    expect($fresh->responded_at)->not->toBeNull();

    $event = $request->events()->where('type', 'APPROVAL_RESPONDED')->first();

    expect($event)->not->toBeNull();
    expect($event->to_value)->toBe('REJECTED');
});

it('lets the requester cancel a pending approval', function () {
    $org = Organization::factory()->create();
    $manager = makeApprovalManager($org);
    $assignee = makeApprovalAssignee($org);
    $request = approvalRequest($org, $manager);
    $approval = seededApproval($org, $request, $manager, $assignee);

    $token = $manager->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/approvals/{$approval->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'CANCELLED');

    expect($approval->fresh()->status)->toBe(ApprovalStatus::Cancelled);
});

it('lets a manager with approval.cancel cancel someone else approval', function () {
    $org = Organization::factory()->create();
    $manager = makeApprovalManager($org);
    $other = User::factory()->create(['organization_id' => $org->id]);
    grantApprovalPermission($other, 'request.view');
    grantApprovalPermission($other, 'approval.request');
    $request = approvalRequest($org, $other);
    $approval = seededApproval($org, $request, $other, $manager);

    $token = $manager->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/approvals/{$approval->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'CANCELLED');
});

it('forbids a non-requester non-manager from cancelling', function () {
    $org = Organization::factory()->create();
    $manager = makeApprovalManager($org);
    $assignee = makeApprovalAssignee($org);
    $stranger = User::factory()->create(['organization_id' => $org->id]);
    grantApprovalPermission($stranger, 'request.view');
    $request = approvalRequest($org, $manager);
    $approval = seededApproval($org, $request, $manager, $assignee);

    $token = $stranger->createToken('spa')->plainTextToken;

    // The stranger is neither the requester nor holds approval.cancel.
    $this->withToken($token)
        ->postJson("/api/v1/approvals/{$approval->id}/cancel")
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FORBIDDEN');

    expect($approval->fresh()->status)->toBe(ApprovalStatus::Pending);
});

it('rejects cancelling an approval after it was responded to', function () {
    $org = Organization::factory()->create();
    $manager = makeApprovalManager($org);
    $assignee = makeApprovalAssignee($org);
    $request = approvalRequest($org, $manager);
    $approval = seededApproval($org, $request, $manager, $assignee);

    $assigneeToken = $assignee->createToken('spa-assignee')->plainTextToken;

    auth()->forgetGuards();

    $this->withToken($assigneeToken)
        ->postJson("/api/v1/approvals/{$approval->id}/approve")
        ->assertOk();

    $managerToken = $manager->createToken('spa')->plainTextToken;

    auth()->forgetGuards();

    $this->withToken($managerToken)
        ->postJson("/api/v1/approvals/{$approval->id}/cancel")
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'APPROVAL_ALREADY_RESPONDED');

    expect($approval->fresh()->status)->toBe(ApprovalStatus::Approved);
});

it('returns 404 when creating an approval on a foreign service request', function () {
    $orgA = Organization::factory()->create();
    $managerA = makeApprovalManager($orgA);
    $assigneeA = makeApprovalAssignee($orgA);

    $orgB = Organization::factory()->create();
    $userB = makeApprovalManager($orgB);
    $requestB = approvalRequest($orgB, $userB);

    $token = $managerA->createToken('spa')->plainTextToken;

    // The assignee is an org-A user so the request passes validation; the
    // controller's org-scope guard is what turns the foreign URL into a 404.
    $this->withToken($token)
        ->postJson("/api/v1/service-requests/{$requestB->id}/approvals", [
            'assigned_to' => $assigneeA->id,
            'type' => 'ACCEPTANCE',
            'title' => 'Intrusion attempt',
        ])
        ->assertNotFound()
        ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');

    expect(Approval::query()->count())->toBe(0);
});

it('notifies the assignee on request and the requester on response', function () {
    $org = Organization::factory()->create();
    $manager = makeApprovalManager($org);
    $assignee = makeApprovalAssignee($org);
    $request = approvalRequest($org, $manager);

    $managerToken = $manager->createToken('spa')->plainTextToken;

    $this->withToken($managerToken)
        ->postJson("/api/v1/service-requests/{$request->id}/approvals", [
            'assigned_to' => $assignee->id,
            'type' => 'QUOTATION',
            'title' => 'Notify round trip',
        ])
        ->assertCreated();

    $approval = Approval::query()->firstOrFail();

    $requestedNotification = Notification::query()->firstOrFail();

    expect($requestedNotification->user_id)->toBe($assignee->id);
    expect($requestedNotification->type)->toBe(NotificationType::APPROVAL_REQUESTED);
    expect($requestedNotification->actor_id)->toBe($manager->id);
    expect($requestedNotification->resource_type)->toBe('approval');
    expect($requestedNotification->resource_id)->toBe($approval->id);
    expect($requestedNotification->data['approval_id'])->toBe($approval->id);

    $assigneeToken = $assignee->createToken('spa-assignee')->plainTextToken;

    auth()->forgetGuards();

    $this->withToken($assigneeToken)
        ->postJson("/api/v1/approvals/{$approval->id}/approve")
        ->assertOk();

    $respondedNotification = Notification::query()
        ->where('type', NotificationType::APPROVAL_RESPONDED)
        ->firstOrFail();

    expect($respondedNotification->user_id)->toBe($manager->id);
    expect($respondedNotification->actor_id)->toBe($assignee->id);
    expect($respondedNotification->resource_type)->toBe('approval');
    expect($respondedNotification->resource_id)->toBe($approval->id);
    expect($respondedNotification->data['response'])->toBe('APPROVED');
});

it('expires pending approvals past their due date exactly once', function () {
    $org = Organization::factory()->create();
    $manager = makeApprovalManager($org);
    $assignee = makeApprovalAssignee($org);
    $request = approvalRequest($org, $manager);

    $overdue = seededApproval($org, $request, $manager, $assignee, [
        'due_at' => now()->subHour(),
        'title' => 'Overdue',
    ]);
    $dueLater = seededApproval($org, $request, $manager, $assignee, [
        'due_at' => now()->addHour(),
        'title' => 'Not yet due',
    ]);
    $noDue = seededApproval($org, $request, $manager, $assignee, [
        'due_at' => null,
        'title' => 'No due date',
    ]);
    $alreadyApproved = seededApproval($org, $request, $manager, $assignee, [
        'status' => ApprovalStatus::Approved,
        'responded_at' => now(),
        'due_at' => now()->subDay(),
        'title' => 'Already decided',
    ]);

    (new ExpirePendingApprovals)->handle();

    expect($overdue->fresh()->status)->toBe(ApprovalStatus::Expired);
    expect($dueLater->fresh()->status)->toBe(ApprovalStatus::Pending);
    expect($noDue->fresh()->status)->toBe(ApprovalStatus::Pending);
    expect($alreadyApproved->fresh()->status)->toBe(ApprovalStatus::Approved);

    // A second run is idempotent: no PENDING overdue rows are left to touch.
    (new ExpirePendingApprovals)->handle();

    expect(Approval::query()->where('status', ApprovalStatus::Expired)->count())->toBe(1);
});

it('requires approval.respond permission to approve', function () {
    $org = Organization::factory()->create();
    $manager = makeApprovalManager($org);
    $assignee = User::factory()->create(['organization_id' => $org->id]);
    grantApprovalPermission($assignee, 'request.view');
    $request = approvalRequest($org, $manager);
    $approval = seededApproval($org, $request, $manager, $assignee);

    $token = $assignee->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/approvals/{$approval->id}/approve")
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FORBIDDEN');

    expect($approval->fresh()->status)->toBe(ApprovalStatus::Pending);
});
