<?php

use App\Enums\ServiceRequestEventType;
use App\Enums\ServiceRequestPriority;
use App\Enums\ServiceRequestStatus;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestCategory;
use App\Models\User;

/**
 * Grant the given user a single permission by attaching it through a fresh
 * role, mirroring how permission middleware checks real DB rows.
 */
function grantServiceRequestCrudPermission(User $user, string $permission): void
{
    $permissionModel = Permission::firstOrCreate(
        ['name' => $permission],
        ['group' => explode('.', $permission)[0], 'description' => ucfirst($permission)],
    );

    $role = Role::factory()->create(['organization_id' => $user->organization_id]);
    $role->permissions()->attach($permissionModel->name);

    $user->roles()->attach($role->id);
}

it('creates a service request with a REQ-year-000001 number', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    grantServiceRequestCrudPermission($admin, 'request.create');

    $customer = Customer::factory()->create(['organization_id' => $org->id]);
    $category = ServiceRequestCategory::factory()->create(['organization_id' => $org->id]);

    $token = $admin->createToken('spa')->plainTextToken;

    $response = $this->withToken($token)
        ->postJson('/api/v1/service-requests', [
            'customer_id' => $customer->id,
            'category_id' => $category->id,
            'title' => 'Cannot access the invoicing portal',
            'description' => 'Login succeeds but the portal returns a blank page.',
            'priority' => 'HIGH',
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.title', 'Cannot access the invoicing portal')
        ->assertJsonPath('data.status', 'NEW')
        ->assertJsonPath('data.priority', 'HIGH')
        ->assertJsonPath('data.version', 1)
        ->assertJsonPath('data.customer.id', $customer->id);

    $number = $response->json('data.request_number');
    expect($number)->toBeString()
        ->toMatch('/^REQ-'.now()->format('Y').'-\d{6}$/');

    $request = ServiceRequest::query()->where('request_number', $number)->first();

    expect($request)->not->toBeNull();
    expect($request->organization_id)->toBe($org->id);
    expect($request->requested_by)->toBe($admin->id);
    expect($request->status)->toBe(ServiceRequestStatus::New);
});

it('auto-assigns priority MEDIUM and status NEW when omitted', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    grantServiceRequestCrudPermission($admin, 'request.create');

    $customer = Customer::factory()->create(['organization_id' => $org->id]);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/service-requests', [
            'customer_id' => $customer->id,
            'title' => 'No priority supplied',
            'description' => 'Defaults should kick in.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'NEW')
        ->assertJsonPath('data.priority', 'MEDIUM');

    expect(ServiceRequest::query()->where('organization_id', $org->id)->value('priority'))
        ->toBe(ServiceRequestPriority::Medium);
});

it('infers the customer from a customer contact when none is supplied', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $org->id]);
    grantServiceRequestCrudPermission($user, 'request.create');

    $customer = Customer::factory()->create(['organization_id' => $org->id]);
    CustomerContact::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'user_id' => $user->id,
    ]);

    $token = $user->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/service-requests', [
            'title' => 'Portal user raises a request',
            'description' => 'No customer id is sent by the customer portal.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.customer.id', $customer->id);
});

it('requires a customer when the user has no customer contact', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $org->id]);
    grantServiceRequestCrudPermission($user, 'request.create');

    $token = $user->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/service-requests', [
            'title' => 'Missing customer',
            'description' => 'This user is not a customer contact.',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['error' => ['fields' => ['customer_id']]]);
});

it('lists service requests of the organization with pagination meta', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    grantServiceRequestCrudPermission($admin, 'request.view');

    $customer = Customer::factory()->create(['organization_id' => $org->id]);
    ServiceRequest::factory()->count(3)->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'requested_by' => $admin->id,
    ]);

    $token = $admin->createToken('spa')->plainTextToken;

    $response = $this->withToken($token)
        ->getJson('/api/v1/service-requests');

    $response
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'request_number', 'title', 'status', 'priority']],
            'meta' => ['current_page', 'per_page', 'total', 'last_page', 'request_id'],
        ])
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.per_page', 15);
});

it('searches service requests by title and description', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    grantServiceRequestCrudPermission($admin, 'request.view');

    $customer = Customer::factory()->create(['organization_id' => $org->id]);
    ServiceRequest::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'requested_by' => $admin->id,
        'title' => 'VPN connection broken',
        'description' => 'Cannot reach the office network.',
    ]);
    ServiceRequest::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'requested_by' => $admin->id,
        'title' => 'Printer out of ink',
        'description' => 'The marketing printer is flashing red.',
    ]);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/service-requests?search=VPN')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'VPN connection broken');

    $this->withToken($token)
        ->getJson('/api/v1/service-requests?search=flashing')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Printer out of ink');
});

it('filters service requests by status priority and assignee', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    grantServiceRequestCrudPermission($admin, 'request.view');

    $customer = Customer::factory()->create(['organization_id' => $org->id]);
    $assignee = User::factory()->create(['organization_id' => $org->id]);

    ServiceRequest::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'requested_by' => $admin->id,
        'assigned_to' => $assignee->id,
        'title' => 'High priority inbox',
        'status' => ServiceRequestStatus::InProgress,
        'priority' => ServiceRequestPriority::High,
    ]);
    ServiceRequest::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'requested_by' => $admin->id,
        'title' => 'Low priority backlog',
        'status' => ServiceRequestStatus::New,
        'priority' => ServiceRequestPriority::Low,
    ]);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/service-requests?status[]=IN_PROGRESS&priority[]=HIGH')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'High priority inbox');

    $this->withToken($token)
        ->getJson('/api/v1/service-requests?assigned_to='.$assignee->id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'High priority inbox');
});

it('sorts service requests newest first with sort=-created_at', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    grantServiceRequestCrudPermission($admin, 'request.view');

    $customer = Customer::factory()->create(['organization_id' => $org->id]);

    $first = ServiceRequest::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'requested_by' => $admin->id,
        'title' => 'Oldest request',
    ]);
    $second = ServiceRequest::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'requested_by' => $admin->id,
        'title' => 'Newest request',
    ]);

    // created_at is guarded, so force it to control the sort order.
    $first->forceFill(['created_at' => now()->subMinute()])->save();
    $second->forceFill(['created_at' => now()->addMinute()])->save();

    $token = $admin->createToken('spa')->plainTextToken;

    $response = $this->withToken($token)
        ->getJson('/api/v1/service-requests?sort=-created_at');

    $response
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.title', 'Newest request')
        ->assertJsonPath('data.1.title', 'Oldest request');
});

it('does not reveal service requests from another organization', function () {
    $orgA = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $orgA->id]);
    grantServiceRequestCrudPermission($admin, 'request.view');

    $customerA = Customer::factory()->create(['organization_id' => $orgA->id]);
    ServiceRequest::factory()->create([
        'organization_id' => $orgA->id,
        'customer_id' => $customerA->id,
        'requested_by' => $admin->id,
        'title' => 'Our request',
    ]);

    $orgB = Organization::factory()->create();
    $customerB = Customer::factory()->create(['organization_id' => $orgB->id]);
    $foreignUser = User::factory()->create(['organization_id' => $orgB->id]);
    ServiceRequest::factory()->create([
        'organization_id' => $orgB->id,
        'customer_id' => $customerB->id,
        'requested_by' => $foreignUser->id,
        'title' => 'Their request',
    ]);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/service-requests')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.total', 1)
        ->assertJsonMissing(['title' => 'Their request'])
        ->assertJsonFragment(['title' => 'Our request']);
});

it('returns 404 when a service request from another organization is requested', function () {
    $orgA = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $orgA->id]);
    grantServiceRequestCrudPermission($admin, 'request.view');

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
        ->getJson("/api/v1/service-requests/{$foreign->id}")
        ->assertNotFound()
        ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
});

it('rejects creating a request for a foreign customer', function () {
    $orgA = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $orgA->id]);
    grantServiceRequestCrudPermission($admin, 'request.create');

    $orgB = Organization::factory()->create();
    $foreignCustomer = Customer::factory()->create(['organization_id' => $orgB->id]);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/service-requests', [
            'customer_id' => $foreignCustomer->id,
            'title' => 'Intrusion',
            'description' => 'Should fail.',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['error' => ['fields' => ['customer_id']]]);

    expect(ServiceRequest::query()->count())->toBe(0);
});

it('rejects a foreign category when creating a request', function () {
    $orgA = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $orgA->id]);
    grantServiceRequestCrudPermission($admin, 'request.create');

    $customer = Customer::factory()->create(['organization_id' => $orgA->id]);

    $orgB = Organization::factory()->create();
    $foreignCategory = ServiceRequestCategory::factory()->create(['organization_id' => $orgB->id]);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/service-requests', [
            'customer_id' => $customer->id,
            'category_id' => $foreignCategory->id,
            'title' => 'Bad category',
            'description' => 'Should fail.',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['error' => ['fields' => ['category_id']]]);
});

it('rejects listing requests without the request.view permission', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $org->id]);

    $token = $user->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/service-requests')
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FORBIDDEN');
});

it('shows a service request with its relations', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    grantServiceRequestCrudPermission($admin, 'request.view');

    $customer = Customer::factory()->create(['organization_id' => $org->id]);
    $category = ServiceRequestCategory::factory()->create(['organization_id' => $org->id]);
    $requester = User::factory()->create(['organization_id' => $org->id]);
    $request = ServiceRequest::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'requested_by' => $requester->id,
        'category_id' => $category->id,
        'title' => 'Visible detail',
    ]);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->getJson("/api/v1/service-requests/{$request->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $request->id)
        ->assertJsonPath('data.title', 'Visible detail')
        ->assertJsonPath('data.customer.id', $customer->id)
        ->assertJsonPath('data.category.id', $category->id)
        ->assertJsonPath('data.requested_by.id', $requester->id)
        ->assertJsonPath('data.version', 1);
});

it('updates the title description and category of a mutable request', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    grantServiceRequestCrudPermission($admin, 'request.update');

    $customer = Customer::factory()->create(['organization_id' => $org->id]);
    $oldCategory = ServiceRequestCategory::factory()->create(['organization_id' => $org->id, 'name' => 'Old category']);
    $newCategory = ServiceRequestCategory::factory()->create(['organization_id' => $org->id, 'name' => 'New category']);
    $request = ServiceRequest::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'requested_by' => $admin->id,
        'category_id' => $oldCategory->id,
    ]);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->patchJson("/api/v1/service-requests/{$request->id}", [
            'title' => 'Renamed request',
            'description' => 'Updated description.',
            'category_id' => $newCategory->id,
            'version' => 1,
        ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Renamed request')
        ->assertJsonPath('data.description', 'Updated description.')
        ->assertJsonPath('data.category.id', $newCategory->id)
        ->assertJsonPath('data.version', 2);

    $fresh = $request->fresh();

    expect($fresh->category_id)->toBe($newCategory->id);
    expect($fresh->version)->toBe(2);
});

it('rejects updating a request with a stale version', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    grantServiceRequestCrudPermission($admin, 'request.update');

    $customer = Customer::factory()->create(['organization_id' => $org->id]);
    $request = ServiceRequest::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'requested_by' => $admin->id,
    ]);

    $token = $admin->createToken('spa')->plainTextToken;

    // First writer bumps the version to 2.
    $this->withToken($token)
        ->patchJson("/api/v1/service-requests/{$request->id}", [
            'title' => 'First edit',
            'version' => 1,
        ])
        ->assertOk()
        ->assertJsonPath('data.version', 2);

    // Second writer still echoes version 1 — optimistic lock trips.
    $this->withToken($token)
        ->patchJson("/api/v1/service-requests/{$request->id}", [
            'title' => 'Concurrent edit',
            'version' => 1,
        ])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'RESOURCE_VERSION_CONFLICT');

    expect($request->fresh()->title)->toBe('First edit');
});

it('records a creation event on the request timeline', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    grantServiceRequestCrudPermission($admin, 'request.create');

    $customer = Customer::factory()->create(['organization_id' => $org->id]);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/service-requests', [
            'customer_id' => $customer->id,
            'title' => 'Timeline seed',
            'description' => 'Should leave one event row.',
        ])
        ->assertCreated();

    $request = ServiceRequest::query()->firstOrFail();

    expect($request->events()->count())->toBe(1);
    expect($request->events()->value('type'))->toBe(ServiceRequestEventType::StatusChanged);
    expect($request->events()->value('to_value'))->toBe('NEW');
});
