<?php

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

it('logs a user in and returns a token and authorization context', function () {
    $org = Organization::factory()->create();
    $role = Role::factory()->create(['organization_id' => $org->id]);
    $permission = Permission::firstOrCreate(
        ['name' => 'request.view'],
        ['group' => 'request', 'description' => 'View requests'],
    );
    $role->permissions()->attach($permission->name);
    $user = User::factory()->create([
        'organization_id' => $org->id,
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);
    $user->roles()->attach($role->id);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $response
        ->assertOk()
        ->assertJsonStructure(['data' => ['token', 'user']]);

    expect($response->json('data.user.email'))->toBe('admin@example.com');
});

it('returns the current user with organization roles and permissions', function () {
    $org = Organization::factory()->create();
    $role = Role::factory()->create(['organization_id' => $org->id]);
    $role->permissions()->attach(Permission::firstOrCreate(
        ['name' => 'user.view'],
        ['group' => 'user', 'description' => 'View users'],
    ));
    $user = User::factory()->create([
        'organization_id' => $org->id,
        'email' => 'manager@example.com',
    ]);
    $user->roles()->attach($role->id);

    $token = $user->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'manager@example.com')
        ->assertJsonPath('data.organization.id', $org->id)
        ->assertJsonCount(1, 'data.roles')
        ->assertJsonPath('data.permissions.0', 'user.view');
});

it('rejects invalid credentials without revealing email existence', function () {
    Organization::factory()->create();
    User::factory()->create([
        'email' => 'ghost@example.com',
        'password' => 'correct-password',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'ghost@example.com',
        'password' => 'wrong-password',
    ])
        ->assertUnprocessable()
        ->assertJsonStructure(['error' => ['message']]);
});

it('does not log in a disabled user', function () {
    $user = User::factory()->disabled()->create([
        'email' => 'disabled@example.com',
        'password' => 'password',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'disabled@example.com',
        'password' => 'password',
    ])
        ->assertUnprocessable();
});

it('does not log in a user whose organization is suspended', function () {
    $org = Organization::factory()->suspended()->create();
    $user = User::factory()->create([
        'organization_id' => $org->id,
        'email' => 'suspended@example.com',
        'password' => 'password',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'suspended@example.com',
        'password' => 'password',
    ])
        ->assertUnprocessable();
});

it('logs out and revokes the current token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/auth/logout')
        ->assertOk();

    expect($user->tokens()->count())->toBe(0);
});

it('requires authentication for /me', function () {
    $this->getJson('/api/v1/me')
        ->assertUnauthorized();
});
