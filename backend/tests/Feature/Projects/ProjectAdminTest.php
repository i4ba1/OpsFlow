<?php

use App\Enums\ProjectStatus;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;

/**
 * Grant the given user a single permission by attaching it through a fresh
 * role, mirroring how permission middleware checks real DB rows.
 */
function grantProjectAdminPermission(User $user, string $permission): void
{
    $permissionModel = Permission::firstOrCreate(
        ['name' => $permission],
        ['group' => explode('.', $permission)[0], 'description' => ucfirst($permission)],
    );

    $role = Role::factory()->create(['organization_id' => $user->organization_id]);
    $role->permissions()->attach($permissionModel->name);

    $user->roles()->attach($role->id);
}

it('creates a project with an auto-generated PRJ code', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    grantProjectAdminPermission($admin, 'project.create');

    $customer = Customer::factory()->create(['organization_id' => $org->id]);

    $token = $admin->createToken('spa')->plainTextToken;

    $response = $this->withToken($token)
        ->postJson('/api/v1/projects', [
            'customer_id' => $customer->id,
            'name' => 'Website Revamp',
            'description' => 'Full redesign of the corporate site.',
            'starts_on' => '2026-10-01',
            'ends_on' => '2026-12-31',
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.name', 'Website Revamp')
        ->assertJsonPath('data.status', 'DRAFT')
        ->assertJsonPath('data.customer_id', $customer->id)
        ->assertJsonPath('data.starts_on', '2026-10-01');

    $code = $response->json('data.project_code');
    expect($code)->toBeString()->toStartWith('PRJ-');

    $project = Project::query()->where('project_code', $code)->first();

    expect($project)->not->toBeNull();
    expect($project->organization_id)->toBe($org->id);
    expect($project->customer_id)->toBe($customer->id);
});

it('rejects a project whose customer belongs to another organization', function () {
    $orgA = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $orgA->id]);
    grantProjectAdminPermission($admin, 'project.create');

    $orgB = Organization::factory()->create();
    $foreignCustomer = Customer::factory()->create(['organization_id' => $orgB->id]);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/projects', [
            'customer_id' => $foreignCustomer->id,
            'name' => 'Intrusion Project',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['error' => ['fields' => ['customer_id']]]);

    expect(Project::query()->count())->toBe(0);
});

it('rejects a project whose manager belongs to another organization', function () {
    $orgA = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $orgA->id]);
    grantProjectAdminPermission($admin, 'project.create');

    $customer = Customer::factory()->create(['organization_id' => $orgA->id]);

    $orgB = Organization::factory()->create();
    $foreignManager = User::factory()->create(['organization_id' => $orgB->id]);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/projects', [
            'customer_id' => $customer->id,
            'name' => 'Managed Project',
            'manager_id' => $foreignManager->id,
        ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['error' => ['fields' => ['manager_id']]]);

    expect(Project::query()->count())->toBe(0);
});

it('accepts a manager from the same organization', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    grantProjectAdminPermission($admin, 'project.create');

    $customer = Customer::factory()->create(['organization_id' => $org->id]);
    $manager = User::factory()->create(['organization_id' => $org->id]);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/projects', [
            'customer_id' => $customer->id,
            'name' => 'Managed Project',
            'manager_id' => $manager->id,
        ])
        ->assertCreated()
        ->assertJsonPath('data.manager.id', $manager->id);
});

it('lists projects of the organization with pagination meta', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    grantProjectAdminPermission($admin, 'project.view');

    $customer = Customer::factory()->create(['organization_id' => $org->id]);
    Project::factory()->count(3)->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
    ]);

    $token = $admin->createToken('spa')->plainTextToken;

    $response = $this->withToken($token)
        ->getJson('/api/v1/projects');

    $response
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'project_code', 'name', 'status']],
            'meta' => ['current_page', 'per_page', 'total', 'last_page', 'request_id'],
        ])
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.per_page', 15);
});

it('does not reveal projects from another organization in the list', function () {
    $orgA = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $orgA->id]);
    grantProjectAdminPermission($admin, 'project.view');

    $customer = Customer::factory()->create(['organization_id' => $orgA->id]);
    Project::factory()->create([
        'organization_id' => $orgA->id,
        'customer_id' => $customer->id,
        'name' => 'My Project',
    ]);

    $orgB = Organization::factory()->create();
    $foreignCustomer = Customer::factory()->create(['organization_id' => $orgB->id]);
    Project::factory()->create([
        'organization_id' => $orgB->id,
        'customer_id' => $foreignCustomer->id,
        'name' => 'Their Project',
    ]);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/projects')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.total', 1)
        ->assertJsonMissing(['name' => 'Their Project'])
        ->assertJsonFragment(['name' => 'My Project']);
});

it('filters projects by status and customer', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    grantProjectAdminPermission($admin, 'project.view');

    $customerA = Customer::factory()->create(['organization_id' => $org->id]);
    $customerB = Customer::factory()->create(['organization_id' => $org->id]);

    Project::factory()->active()->create([
        'organization_id' => $org->id,
        'customer_id' => $customerA->id,
        'name' => 'Active Project',
    ]);
    Project::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customerB->id,
        'name' => 'Draft Project',
    ]);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/projects?status[]=ACTIVE&customer_id='.$customerA->id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Active Project');

    $this->withToken($token)
        ->getJson('/api/v1/projects?customer_id='.$customerB->id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Draft Project');
});

it('updates a project', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    grantProjectAdminPermission($admin, 'project.update');

    $customer = Customer::factory()->create(['organization_id' => $org->id]);
    $project = Project::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
    ]);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->patchJson("/api/v1/projects/{$project->id}", [
            'name' => 'Renamed Project',
            'status' => 'ACTIVE',
            'ends_on' => '2026-12-31',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renamed Project')
        ->assertJsonPath('data.status', 'ACTIVE')
        ->assertJsonPath('data.ends_on', '2026-12-31');

    expect($project->fresh()->status)->toBe(ProjectStatus::Active);
});

it('archives a project via the archive endpoint', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    grantProjectAdminPermission($admin, 'project.archive');

    $customer = Customer::factory()->create(['organization_id' => $org->id]);
    $project = Project::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
    ]);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/projects/{$project->id}/archive")
        ->assertOk()
        ->assertJsonPath('data.status', 'ARCHIVED');

    $fresh = $project->fresh();

    expect($fresh)->not->toBeNull();
    expect($fresh->status)->toBe(ProjectStatus::Archived);
    expect($fresh->archived_at)->not->toBeNull();
});

it('returns 404 when archiving a project from another organization', function () {
    $orgA = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $orgA->id]);
    grantProjectAdminPermission($admin, 'project.archive');

    $orgB = Organization::factory()->create();
    $foreignCustomer = Customer::factory()->create(['organization_id' => $orgB->id]);
    $foreignProject = Project::factory()->create([
        'organization_id' => $orgB->id,
        'customer_id' => $foreignCustomer->id,
    ]);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/projects/{$foreignProject->id}/archive")
        ->assertNotFound();

    expect($foreignProject->fresh()->status)->toBe(ProjectStatus::Draft);
});

it('rejects listing projects without the project.view permission', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $org->id]);

    $token = $user->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/projects')
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FORBIDDEN');
});
