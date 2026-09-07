<?php

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

/**
 * Grant the given user a single permission by attaching it through a fresh
 * role, mirroring how permission middleware checks real DB rows.
 */
function grantRoleAdminPermission(User $user, string $permission): void
{
    $permissionModel = Permission::firstOrCreate(
        ['name' => $permission],
        ['group' => explode('.', $permission)[0], 'description' => ucfirst($permission)],
    );

    $role = Role::factory()->create(['organization_id' => $user->organization_id]);
    $role->permissions()->attach($permissionModel->name);

    $user->roles()->attach($role->id);
}

it('lists roles with permission names and pagination meta', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    grantRoleAdminPermission($admin, 'role.view');

    $permission = Permission::firstOrCreate(
        ['name' => 'user.view'],
        ['group' => 'user', 'description' => 'View users'],
    );
    $role = Role::factory()->create(['organization_id' => $org->id]);
    $role->permissions()->attach($permission->name);

    $token = $admin->createToken('spa')->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/v1/roles');

    $response
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'name', 'slug', 'description', 'is_system', 'permissions']],
            'meta' => ['current_page', 'per_page', 'total', 'last_page', 'request_id'],
        ])
        // The grant role for the admin plus the created role.
        ->assertJsonPath('meta.total', 2)
        ->assertJsonCount(2, 'data');
});

it('creates a role with permissions attached', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    grantRoleAdminPermission($admin, 'role.create');

    $permission = Permission::firstOrCreate(
        ['name' => 'user.view'],
        ['group' => 'user', 'description' => 'View users'],
    );

    $token = $admin->createToken('spa')->plainTextToken;

    $response = $this->withToken($token)
        ->postJson('/api/v1/roles', [
            'name' => 'Support Agent',
            'slug' => 'support-agent',
            'description' => 'First line support',
            'permissions' => [$permission->name],
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.name', 'Support Agent')
        ->assertJsonPath('data.slug', 'support-agent')
        ->assertJsonPath('data.is_system', false)
        ->assertJsonPath('data.permissions.0', 'user.view');

    $created = Role::query()->where('slug', 'support-agent')->first();

    expect($created)->not->toBeNull();
    expect($created->organization_id)->toBe($org->id);
    expect($created->permissions->pluck('name')->all())->toBe(['user.view']);
});

it('rejects a duplicate role slug with 422', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    grantRoleAdminPermission($admin, 'role.create');

    Role::factory()->create([
        'organization_id' => $org->id,
        'slug' => 'support-agent',
    ]);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/roles', [
            'name' => 'Another Support',
            'slug' => 'support-agent',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');
});

it('auto-generates a unique slug when none is provided', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    grantRoleAdminPermission($admin, 'role.create');

    Role::factory()->create([
        'organization_id' => $org->id,
        'name' => 'Operations Team',
        'slug' => 'operations-team',
    ]);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/roles', [
            'name' => 'Operations Team',
        ])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'operations-team-2');
});

it('updates role name description and permissions', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    grantRoleAdminPermission($admin, 'role.update');

    $oldPermission = Permission::firstOrCreate(
        ['name' => 'user.view'],
        ['group' => 'user', 'description' => 'View users'],
    );
    $newPermission = Permission::firstOrCreate(
        ['name' => 'user.create'],
        ['group' => 'user', 'description' => 'Invite users'],
    );

    $role = Role::factory()->create([
        'organization_id' => $org->id,
        'name' => 'Old Name',
    ]);
    $role->permissions()->attach($oldPermission->id);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->patchJson("/api/v1/roles/{$role->id}", [
            'name' => 'New Name',
            'description' => 'Updated description',
            'permissions' => [$newPermission->name],
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'New Name')
        ->assertJsonPath('data.description', 'Updated description')
        ->assertJsonPath('data.permissions.0', 'user.create');

    expect($role->fresh()->permissions->pluck('name')->all())->toBe(['user.create']);
});

it('rejects changing the name of a system role', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    grantRoleAdminPermission($admin, 'role.update');

    $role = Role::factory()->create([
        'organization_id' => $org->id,
        'name' => 'System Admin',
        'slug' => 'system-admin',
        'is_system' => true,
    ]);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->patchJson("/api/v1/roles/{$role->id}", ['name' => 'Hacked'])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FORBIDDEN');

    expect($role->fresh()->name)->toBe('System Admin');
});

it('rejects deleting a role that is still assigned to users', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    grantRoleAdminPermission($admin, 'role.delete');

    $role = Role::factory()->create(['organization_id' => $org->id]);
    $member = User::factory()->create(['organization_id' => $org->id]);
    $member->roles()->attach($role->id);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->deleteJson("/api/v1/roles/{$role->id}")
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'ROLE_IN_USE');

    expect(Role::query()->whereKey($role->id)->exists())->toBeTrue();
});

it('rejects deleting a system role', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    grantRoleAdminPermission($admin, 'role.delete');

    $role = Role::factory()->create([
        'organization_id' => $org->id,
        'is_system' => true,
    ]);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->deleteJson("/api/v1/roles/{$role->id}")
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FORBIDDEN');

    expect(Role::query()->whereKey($role->id)->exists())->toBeTrue();
});

it('deletes an unused non-system role', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    grantRoleAdminPermission($admin, 'role.delete');

    $role = Role::factory()->create(['organization_id' => $org->id]);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->deleteJson("/api/v1/roles/{$role->id}")
        ->assertNoContent();

    expect(Role::query()->whereKey($role->id)->exists())->toBeFalse();
});

it('returns the permission catalog grouped by module', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    grantRoleAdminPermission($admin, 'role.view');

    $token = $admin->createToken('spa')->plainTextToken;

    $response = $this->withToken($token)
        ->getJson('/api/v1/roles/permissions-catalog');

    $response->assertOk();

    $groups = collect($response->json('data'))->pluck('group');

    expect($groups)->toContain('organization', 'user', 'role');
    expect($response->json('data.0'))->toHaveKeys(['group', 'permissions']);
    expect(collect($response->json('data'))->firstWhere('group', 'user')['permissions'])
        ->toContain('user.view', 'user.manage_roles');
});

it('returns 404 when a role from another organization is requested', function () {
    $orgA = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $orgA->id]);
    grantRoleAdminPermission($admin, 'role.update');

    $orgB = Organization::factory()->create();
    $foreignRole = Role::factory()->create(['organization_id' => $orgB->id]);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->patchJson("/api/v1/roles/{$foreignRole->id}", ['name' => 'Intrusion'])
        ->assertNotFound()
        ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
});

it('does not reveal roles from another organization in the list', function () {
    $orgA = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $orgA->id]);
    grantRoleAdminPermission($admin, 'role.view');

    Role::factory()->create(['organization_id' => $orgA->id, 'name' => 'Mine']);

    $orgB = Organization::factory()->create();
    Role::factory()->create(['organization_id' => $orgB->id, 'name' => 'Theirs']);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/roles')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonMissing(['name' => 'Theirs'])
        ->assertJsonFragment(['name' => 'Mine']);
});

it('rejects role management without the role permission', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $org->id]);

    $token = $user->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/roles')
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FORBIDDEN');
});
