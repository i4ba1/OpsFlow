<?php

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Support\Audit\AuditRecorder;

/**
 * Grant the given user a single permission through a fresh role.
 */
function grantAuditPermission(User $user, string $permission): void
{
    $permissionModel = Permission::firstOrCreate(
        ['name' => $permission],
        ['group' => explode('.', $permission)[0], 'description' => ucfirst($permission)],
    );

    $role = Role::query()->firstOrCreate(
        ['organization_id' => $user->organization_id, 'slug' => 'audit-'.$permission],
        ['name' => 'Audit '.$permission, 'description' => null],
    );
    $role->permissions()->syncWithoutDetaching($permissionModel->name);
    $user->roles()->syncWithoutDetaching($role->id);
}

function makeAuditViewer(Organization $org): User
{
    $user = User::factory()->create(['organization_id' => $org->id]);
    grantAuditPermission($user, 'audit.view');

    return $user;
}

it('records a status transition in the audit log', function () {
    $org = Organization::factory()->create();
    $manager = makeAuditViewer($org);
    grantAuditPermission($manager, 'request.transition');
    $customer = Customer::factory()->create(['organization_id' => $org->id]);
    $request = ServiceRequest::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'requested_by' => $manager->id,
    ]);

    $this->withToken($manager->createToken('spa')->plainTextToken)
        ->postJson("/api/v1/service-requests/{$request->id}/transition", ['status' => 'TRIAGED'])
        ->assertOk();

    expect(AuditLog::query()->where('action', 'request.status_changed')->count())->toBe(1);

    $entry = AuditLog::query()->where('action', 'request.status_changed')->firstOrFail();

    expect($entry->entity_type)->toBe('service_request')
        ->and($entry->entity_id)->toBe($request->id)
        ->and($entry->actor_id)->toBe($manager->id)
        ->and($entry->new_values['status'])->toBe('TRIAGED')
        ->and($entry->old_values['status'])->toBe('NEW');
});

it('records customer updates through the API with request context', function () {
    $org = Organization::factory()->create();
    $manager = makeAuditViewer($org);
    grantAuditPermission($manager, 'customer.update');

    $customer = Customer::factory()->create([
        'organization_id' => $org->id,
        'name' => 'Old Name',
    ]);

    $this->withToken($manager->createToken('spa')->plainTextToken)
        ->patchJson("/api/v1/customers/{$customer->id}", ['name' => 'New Name'])
        ->assertOk();

    $entry = AuditLog::query()->where('action', 'customer.updated')->firstOrFail();

    expect($entry->old_values['name'])->toBe('Old Name')
        ->and($entry->new_values['name'])->toBe('New Name')
        ->and($entry->actor_id)->toBe($manager->id)
        ->and($entry->ip_address)->not->toBeNull();
});

it('lists audit entries org-scoped with pagination and requires permission', function () {
    $orgA = Organization::factory()->create();
    $viewerA = makeAuditViewer($orgA);

    $orgB = Organization::factory()->create();
    $managerB = User::factory()->create(['organization_id' => $orgB->id]);

    app(AuditRecorder::class)->record($viewerA, 'user.updated', 'user', '11111111-1111-1111-1111-111111111111');
    app(AuditRecorder::class)->record($viewerA, 'customer.updated', 'customer', '22222222-2222-2222-2222-222222222222');
    app(AuditRecorder::class)->record($managerB, 'user.updated', 'user', '33333333-3333-3333-3333-333333333333');

    $response = $this->withToken($viewerA->createToken('spa')->plainTextToken)
        ->getJson('/api/v1/audit-logs')
        ->assertOk()
        ->assertJsonStructure(['data', 'meta' => ['total', 'request_id']])
        ->assertJsonPath('meta.total', 2);

    expect(collect($response->json('data'))->pluck('action')->all())
        ->toContain('user.updated', 'customer.updated');

    // Filter by action.
    $this->withToken($viewerA->createToken('spa')->plainTextToken)
        ->getJson('/api/v1/audit-logs?action=customer.updated')
        ->assertOk()
        ->assertJsonPath('meta.total', 1);

    // Permission required. Reset the stateful auth guard so the next request
    // authenticates as the plain user rather than the cached viewer.
    auth()->forgetGuards();

    $plain = User::factory()->create(['organization_id' => $orgA->id]);
    $this->withToken($plain->createToken('spa')->plainTextToken)
        ->getJson('/api/v1/audit-logs')
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FORBIDDEN');
});

it('redacts sensitive values before persisting', function () {
    $org = Organization::factory()->create();
    $actor = User::factory()->create(['organization_id' => $org->id]);

    app(AuditRecorder::class)->record(
        $actor,
        'user.updated',
        'user',
        $actor->id,
        oldValues: ['password' => 'super-secret', 'name' => 'A'],
        newValues: ['password' => 'changed', 'name' => 'B'],
    );

    $entry = AuditLog::query()->firstOrFail();

    expect($entry->new_values['password'])->toBe('[REDACTED]')
        ->and($entry->new_values['name'])->toBe('B');
});
