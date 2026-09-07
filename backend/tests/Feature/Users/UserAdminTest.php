<?php

use App\Enums\UserStatus;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

/**
 * Grant the given user a single permission by attaching it through a fresh
 * role, mirroring how permission middleware checks real DB rows.
 */
function grantUserAdminPermission(User $user, string $permission): void
{
    $permissionModel = Permission::firstOrCreate(
        ['name' => $permission],
        ['group' => explode('.', $permission)[0], 'description' => ucfirst($permission)],
    );

    $role = Role::factory()->create(['organization_id' => $user->organization_id]);
    $role->permissions()->attach($permissionModel->name);

    $user->roles()->attach($role->id);
}

it('lists users of the organization with pagination meta', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    grantUserAdminPermission($admin, 'user.view');

    User::factory()->count(3)->create(['organization_id' => $org->id]);

    $token = $admin->createToken('spa')->plainTextToken;

    $response = $this->withToken($token)
        ->getJson('/api/v1/users');

    $response
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'name', 'email', 'status', 'timezone']],
            'meta' => ['current_page', 'per_page', 'total', 'last_page', 'request_id'],
        ])
        ->assertJsonCount(4, 'data')
        ->assertJsonPath('meta.total', 4)
        ->assertJsonPath('meta.per_page', 15);
});

it('searches users by name and filters by status', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    grantUserAdminPermission($admin, 'user.view');

    User::factory()->create([
        'organization_id' => $org->id,
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ]);
    User::factory()->create([
        'organization_id' => $org->id,
        'name' => 'Grace Hopper',
        'email' => 'grace@example.com',
    ]);
    User::factory()->disabled()->create([
        'organization_id' => $org->id,
        'name' => 'Linus Torvalds',
        'email' => 'linus@example.com',
    ]);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/users?search=ada&status=ACTIVE')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.email', 'ada@example.com');

    $this->withToken($token)
        ->getJson('/api/v1/users?status=DISABLED')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Linus Torvalds');
});

it('does not reveal users from another organization in the list', function () {
    $orgA = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $orgA->id]);
    grantUserAdminPermission($admin, 'user.view');

    User::factory()->create([
        'organization_id' => $orgA->id,
        'email' => 'mine@example.com',
    ]);

    $orgB = Organization::factory()->create();
    User::factory()->create(['organization_id' => $orgB->id, 'email' => 'other@example.com']);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/users')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 2);
});

it('rejects listing users without the user.view permission', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $org->id]);

    $token = $user->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/users')
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FORBIDDEN');
});

it('invites a user with INVITED status and optional roles', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    grantUserAdminPermission($admin, 'user.create');

    $role = Role::factory()->create(['organization_id' => $org->id]);

    $token = $admin->createToken('spa')->plainTextToken;

    $response = $this->withToken($token)
        ->postJson('/api/v1/users', [
            'name' => 'New Hire',
            'email' => 'newhire@example.com',
            'role_ids' => [$role->id],
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.name', 'New Hire')
        ->assertJsonPath('data.status', 'INVITED')
        ->assertJsonPath('data.email', 'newhire@example.com')
        ->assertJsonCount(1, 'data.roles');

    $invited = User::query()->where('email', 'newhire@example.com')->first();

    expect($invited)->not->toBeNull();
    expect($invited->status)->toBe(UserStatus::Invited);
    expect($invited->password)->toBeNull();
    expect($invited->roles->pluck('id')->all())->toBe([$role->id]);
});

it('rejects inviting a user whose email already exists anywhere', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    grantUserAdminPermission($admin, 'user.create');

    User::factory()->create([
        'organization_id' => Organization::factory()->create()->id,
        'email' => 'taken@example.com',
    ]);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/users', [
            'name' => 'Duplicate',
            'email' => 'taken@example.com',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['error' => ['fields' => ['email']]]);
});

it('shows a single user of the organization', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    grantUserAdminPermission($admin, 'user.view');

    $target = User::factory()->create([
        'organization_id' => $org->id,
        'name' => 'Visible Person',
    ]);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->getJson("/api/v1/users/{$target->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $target->id)
        ->assertJsonPath('data.name', 'Visible Person');
});

it('returns 404 when a user from another organization is requested', function () {
    $orgA = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $orgA->id]);
    grantUserAdminPermission($admin, 'user.view');

    $orgB = Organization::factory()->create();
    $target = User::factory()->create([
        'organization_id' => $orgB->id,
        'name' => 'Foreign Person',
    ]);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->getJson("/api/v1/users/{$target->id}")
        ->assertNotFound()
        ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
});

it('updates a user name timezone and email', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    grantUserAdminPermission($admin, 'user.update');

    $target = User::factory()->create(['organization_id' => $org->id]);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->patchJson("/api/v1/users/{$target->id}", [
            'name' => 'Renamed',
            'timezone' => 'America/New_York',
            'email' => 'renamed@example.com',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renamed')
        ->assertJsonPath('data.timezone', 'America/New_York')
        ->assertJsonPath('data.email', 'renamed@example.com');

    expect($target->fresh())
        ->name->toBe('Renamed')
        ->timezone->toBe('America/New_York');
});

it('disables and re-activates a user', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    grantUserAdminPermission($admin, 'user.disable');

    $target = User::factory()->create(['organization_id' => $org->id]);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/users/{$target->id}/disable")
        ->assertOk()
        ->assertJsonPath('data.status', 'DISABLED');

    $this->withToken($token)
        ->postJson("/api/v1/users/{$target->id}/activate")
        ->assertOk()
        ->assertJsonPath('data.status', 'ACTIVE');

    expect($target->fresh()->status)->toBe(UserStatus::Active);
});

it('cannot disable your own account', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    grantUserAdminPermission($admin, 'user.disable');

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/users/{$admin->id}/disable")
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FORBIDDEN');

    expect($admin->fresh()->status)->toBe(UserStatus::Active);
});

it('cannot disable a user from another organization', function () {
    $orgA = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $orgA->id]);
    grantUserAdminPermission($admin, 'user.disable');

    $orgB = Organization::factory()->create();
    $target = User::factory()->create(['organization_id' => $orgB->id]);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/users/{$target->id}/disable")
        ->assertNotFound();

    expect($target->fresh()->status)->toBe(UserStatus::Active);
});

it('assigning roles replaces the previous role set', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    grantUserAdminPermission($admin, 'user.manage_roles');

    $target = User::factory()->create(['organization_id' => $org->id]);
    $oldRole = Role::factory()->create(['organization_id' => $org->id]);
    $target->roles()->attach($oldRole->id);

    $newRole = Role::factory()->create(['organization_id' => $org->id]);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->putJson("/api/v1/users/{$target->id}/roles", ['role_ids' => [$newRole->id]])
        ->assertOk()
        ->assertJsonCount(1, 'data.roles')
        ->assertJsonPath('data.roles.0.id', $newRole->id);

    expect($target->fresh()->roles->pluck('id')->all())->toBe([$newRole->id]);
});

it('rejects assigning a foreign-organization role set', function () {
    $orgA = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $orgA->id]);
    grantUserAdminPermission($admin, 'user.manage_roles');

    $target = User::factory()->create(['organization_id' => $orgA->id]);

    $orgB = Organization::factory()->create();
    $foreignRole = Role::factory()->create(['organization_id' => $orgB->id]);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->putJson("/api/v1/users/{$target->id}/roles", ['role_ids' => [$foreignRole->id]])
        ->assertNotFound();

    expect($target->fresh()->roles()->count())->toBe(0);
});

it('requires the user.manage_roles permission to assign roles', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $target = User::factory()->create(['organization_id' => $org->id]);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->putJson("/api/v1/users/{$target->id}/roles", ['role_ids' => []])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FORBIDDEN');
});
