<?php

use App\Enums\ServiceRequestStatus;
use App\Models\Customer;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Support\Notifications\NotificationType;
use Illuminate\Support\Str;

/**
 * Grant the given user a single permission by attaching it through a fresh
 * role, mirroring how permission middleware checks real DB rows.
 */
function grantNotificationPermission(User $user, string $permission): void
{
    $permissionModel = Permission::firstOrCreate(
        ['name' => $permission],
        ['group' => explode('.', $permission)[0], 'description' => ucfirst($permission)],
    );

    $role = Role::factory()->create(['organization_id' => $user->organization_id]);
    $role->permissions()->attach($permissionModel->name);

    $user->roles()->attach($role->id);
}

function makeNotificationOperator(Organization $org): User
{
    $user = User::factory()->create(['organization_id' => $org->id]);

    foreach (['request.view', 'request.create', 'request.assign', 'request.transition', 'request.resolve', 'comment.create'] as $permission) {
        grantNotificationPermission($user, $permission);
    }

    return $user;
}

function notificationRequest(Organization $org, User $requester): ServiceRequest
{
    $customer = Customer::factory()->create(['organization_id' => $org->id]);

    return ServiceRequest::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'requested_by' => $requester->id,
    ]);
}

it('creates a REQUEST_ASSIGNED notification for the assignee', function () {
    $org = Organization::factory()->create();
    $assigner = makeNotificationOperator($org);
    $requester = User::factory()->create(['organization_id' => $org->id]);
    $assignee = User::factory()->create(['organization_id' => $org->id]);

    $request = notificationRequest($org, $requester);

    $token = $assigner->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/service-requests/{$request->id}/assign", [
            'assigned_to' => $assignee->id,
            'note' => 'Yours',
        ])
        ->assertOk();

    $notification = Notification::query()->firstOrFail();

    expect($notification->user_id)->toBe($assignee->id);
    expect($notification->type)->toBe(NotificationType::REQUEST_ASSIGNED);
    expect($notification->actor_id)->toBe($assigner->id);
    expect($notification->resource_type)->toBe('service_request');
    expect($notification->resource_id)->toBe($request->id);
    expect($notification->read_at)->toBeNull();
});

it('does not notify the assigner when assigning to themselves', function () {
    $org = Organization::factory()->create();
    $assigner = makeNotificationOperator($org);
    $requester = User::factory()->create(['organization_id' => $org->id]);
    $request = notificationRequest($org, $requester);

    $token = $assigner->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/service-requests/{$request->id}/assign", [
            'assigned_to' => $assigner->id,
        ])
        ->assertOk();

    expect(Notification::query()->count())->toBe(0);
});

it('notifies the assignee and requester on a comment, never the author', function () {
    $org = Organization::factory()->create();
    $operator = makeNotificationOperator($org);
    $requester = User::factory()->create(['organization_id' => $org->id]);
    $assignee = User::factory()->create(['organization_id' => $org->id]);

    $request = notificationRequest($org, $requester);
    $request->update(['assigned_to' => $assignee->id]);

    $token = $operator->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/service-requests/{$request->id}/comments", [
            'body' => 'Progress update',
        ])
        ->assertCreated();

    $notifications = Notification::query()->get();

    expect($notifications->count())->toBe(2);
    expect($notifications->pluck('user_id')->all())
        ->toContain($assignee->id)
        ->toContain($requester->id)
        ->not->toContain($operator->id);
    expect($notifications->pluck('type')->all())->each->toBe(NotificationType::REQUEST_COMMENT_ADDED);
});

it('notifies only the requester when the assignee writes the comment', function () {
    $org = Organization::factory()->create();
    $operator = makeNotificationOperator($org);
    $requester = User::factory()->create(['organization_id' => $org->id]);

    $request = notificationRequest($org, $requester);
    $request->update(['assigned_to' => $operator->id]);

    $token = $operator->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/service-requests/{$request->id}/comments", [
            'body' => 'From the assignee',
        ])
        ->assertCreated();

    $notifications = Notification::query()->get();

    expect($notifications->count())->toBe(1);
    expect($notifications->first()->user_id)->toBe($requester->id);
});

it('notifies the requester when the request is resolved', function () {
    $org = Organization::factory()->create();
    $operator = makeNotificationOperator($org);
    $requester = User::factory()->create(['organization_id' => $org->id]);

    $request = notificationRequest($org, $requester);
    $request->update([
        'assigned_to' => $operator->id,
        'status' => ServiceRequestStatus::InProgress,
        'first_responded_at' => now(),
    ]);

    $token = $operator->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/service-requests/{$request->id}/transition", [
            'status' => 'RESOLVED',
        ])
        ->assertOk();

    $notification = Notification::query()->firstOrFail();

    expect($notification->user_id)->toBe($requester->id);
    expect($notification->type)->toBe(NotificationType::REQUEST_RESOLVED);
});

it('lists the current user notifications unread first', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $org->id]);
    $actor = User::factory()->create(['organization_id' => $org->id]);

    $read = Notification::query()->create([
        'organization_id' => $org->id,
        'user_id' => $user->id,
        'type' => NotificationType::REQUEST_COMMENT_ADDED,
        'actor_id' => $actor->id,
        'resource_type' => 'service_request',
        'resource_id' => (string) Str::uuid7(),
        'read_at' => now(),
    ]);
    $unread = Notification::query()->create([
        'organization_id' => $org->id,
        'user_id' => $user->id,
        'type' => NotificationType::REQUEST_ASSIGNED,
        'actor_id' => $actor->id,
        'resource_type' => 'service_request',
        'resource_id' => (string) Str::uuid7(),
        'read_at' => null,
    ]);

    // Notifications of other users are invisible.
    $other = User::factory()->create(['organization_id' => $org->id]);
    Notification::query()->create([
        'organization_id' => $org->id,
        'user_id' => $other->id,
        'type' => NotificationType::REQUEST_ASSIGNED,
        'read_at' => null,
    ]);

    $token = $user->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $unread->id)
        ->assertJsonPath('data.0.type', 'REQUEST_ASSIGNED')
        ->assertJsonPath('data.0.read', false)
        ->assertJsonPath('data.0.actor.id', $actor->id)
        ->assertJsonPath('data.1.id', $read->id)
        ->assertJsonPath('data.1.read', true)
        ->assertJsonPath('meta.total', 2);
});

it('reports the unread count', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $org->id]);

    Notification::query()->create([
        'organization_id' => $org->id,
        'user_id' => $user->id,
        'type' => NotificationType::REQUEST_ASSIGNED,
        'read_at' => null,
    ]);
    Notification::query()->create([
        'organization_id' => $org->id,
        'user_id' => $user->id,
        'type' => NotificationType::REQUEST_COMMENT_ADDED,
        'read_at' => now(),
    ]);

    $token = $user->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('data.count', 1);
});

it('marks a single notification read', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $org->id]);

    $notification = Notification::query()->create([
        'organization_id' => $org->id,
        'user_id' => $user->id,
        'type' => NotificationType::REQUEST_ASSIGNED,
        'read_at' => null,
    ]);

    $token = $user->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/notifications/{$notification->id}/read")
        ->assertOk()
        ->assertJsonPath('data.read', true);

    expect($notification->fresh()->read_at)->not->toBeNull();

    $this->withToken($token)
        ->getJson('/api/v1/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('data.count', 0);
});

it('returns 404 when marking a foreign notification read', function () {
    $org = Organization::factory()->create();
    $userA = User::factory()->create(['organization_id' => $org->id]);
    $userB = User::factory()->create(['organization_id' => $org->id]);

    $notification = Notification::query()->create([
        'organization_id' => $org->id,
        'user_id' => $userB->id,
        'type' => NotificationType::REQUEST_ASSIGNED,
        'read_at' => null,
    ]);

    $token = $userA->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/notifications/{$notification->id}/read")
        ->assertNotFound()
        ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');

    expect($notification->fresh()->read_at)->toBeNull();
});

it('marks all notifications read at once', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $org->id]);

    Notification::query()->create([
        'organization_id' => $org->id,
        'user_id' => $user->id,
        'type' => NotificationType::REQUEST_ASSIGNED,
        'read_at' => null,
    ]);
    Notification::query()->create([
        'organization_id' => $org->id,
        'user_id' => $user->id,
        'type' => NotificationType::REQUEST_COMMENT_ADDED,
        'read_at' => null,
    ]);

    $token = $user->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/notifications/read-all')
        ->assertOk()
        ->assertJsonPath('data.updated', 2);

    expect(Notification::query()->whereNull('read_at')->count())->toBe(0);
});
