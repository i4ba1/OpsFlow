<?php

use App\Enums\UserStatus;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

function grantDirectoryPermission(User $user, string $permission): void
{
    $permissionModel = Permission::firstOrCreate(
        ['name' => $permission],
        ['group' => explode('.', $permission)[0], 'description' => ucfirst($permission)],
    );

    $role = Role::factory()->create(['organization_id' => $user->organization_id]);
    $role->permissions()->attach($permissionModel->name);

    $user->roles()->attach($role->id);
}

it('lists active organization users for a user who can assign requests', function () {
    $org = Organization::factory()->create();
    $actor = User::factory()->create(['organization_id' => $org->id]);
    grantDirectoryPermission($actor, 'request.assign');

    User::factory()->create(['organization_id' => $org->id, 'name' => 'Active Ada', 'status' => UserStatus::Active]);
    User::factory()->create(['organization_id' => $org->id, 'name' => 'Disabled Dan', 'status' => UserStatus::Disabled]);

    $otherOrg = Organization::factory()->create();
    User::factory()->create(['organization_id' => $otherOrg->id, 'name' => 'Foreign Fay']);

    $token = $actor->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/directory/users')
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'name', 'email']], 'meta' => ['request_id']])
        ->assertJsonFragment(['name' => 'Active Ada'])
        ->assertJsonMissing(['name' => 'Disabled Dan'])
        ->assertJsonMissing(['name' => 'Foreign Fay']);
});

it('allows approval.request holders to read the directory', function () {
    $org = Organization::factory()->create();
    $actor = User::factory()->create(['organization_id' => $org->id]);
    grantDirectoryPermission($actor, 'approval.request');

    $token = $actor->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/directory/users')
        ->assertOk();
});

it('forbids users without an assign or approval permission', function () {
    $org = Organization::factory()->create();
    $actor = User::factory()->create(['organization_id' => $org->id]);
    grantDirectoryPermission($actor, 'request.view');

    $token = $actor->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/directory/users')
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FORBIDDEN');
});
