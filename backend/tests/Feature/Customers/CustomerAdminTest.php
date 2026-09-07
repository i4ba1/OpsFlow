<?php

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

/**
 * Grant the given user a single permission by attaching it through a fresh
 * role, mirroring how permission middleware checks real DB rows.
 */
function grantCustomerAdminPermission(User $user, string $permission): void
{
    $permissionModel = Permission::firstOrCreate(
        ['name' => $permission],
        ['group' => explode('.', $permission)[0], 'description' => ucfirst($permission)],
    );

    $role = Role::factory()->create(['organization_id' => $user->organization_id]);
    $role->permissions()->attach($permissionModel->name);

    $user->roles()->attach($role->id);
}

it('creates a customer with an auto-generated CUS code', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    grantCustomerAdminPermission($admin, 'customer.create');

    $token = $admin->createToken('spa')->plainTextToken;

    $response = $this->withToken($token)
        ->postJson('/api/v1/customers', [
            'name' => 'Acme Corporation',
            'legal_name' => 'Acme Corporation Pte Ltd',
            'email' => 'billing@acme.example',
            'phone' => '+65 6123 4567',
            'city' => 'Singapore',
            'country_code' => 'SG',
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.name', 'Acme Corporation')
        ->assertJsonPath('data.country_code', 'SG')
        ->assertJsonPath('data.status', 'ACTIVE');

    $code = $response->json('data.customer_code');
    expect($code)->toBeString()->toStartWith('CUS-');

    $customer = Customer::query()->where('customer_code', $code)->first();

    expect($customer)->not->toBeNull();
    expect($customer->organization_id)->toBe($org->id);
    expect($customer->created_by)->toBe($admin->id);
});

it('lists customers of the organization with pagination meta', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    grantCustomerAdminPermission($admin, 'customer.view');

    Customer::factory()->count(3)->create(['organization_id' => $org->id]);

    $token = $admin->createToken('spa')->plainTextToken;

    $response = $this->withToken($token)
        ->getJson('/api/v1/customers');

    $response
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'customer_code', 'name', 'status']],
            'meta' => ['current_page', 'per_page', 'total', 'last_page', 'request_id'],
        ])
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.per_page', 15);
});

it('does not reveal customers from another organization in the list', function () {
    $orgA = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $orgA->id]);
    grantCustomerAdminPermission($admin, 'customer.view');

    Customer::factory()->create(['organization_id' => $orgA->id, 'name' => 'Mine Co']);

    $orgB = Organization::factory()->create();
    Customer::factory()->create(['organization_id' => $orgB->id, 'name' => 'Foreign Co']);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/customers')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.total', 1)
        ->assertJsonMissing(['name' => 'Foreign Co'])
        ->assertJsonFragment(['name' => 'Mine Co']);
});

it('rejects listing customers without the customer.view permission', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $org->id]);

    $token = $user->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/customers')
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FORBIDDEN');
});

it('shows a customer of the organization', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    grantCustomerAdminPermission($admin, 'customer.view');

    $customer = Customer::factory()->create([
        'organization_id' => $org->id,
        'name' => 'Visible Customer',
    ]);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->getJson("/api/v1/customers/{$customer->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $customer->id)
        ->assertJsonPath('data.name', 'Visible Customer')
        ->assertJsonPath('data.contacts_count', 0);
});

it('returns 404 when a customer from another organization is requested', function () {
    $orgA = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $orgA->id]);
    grantCustomerAdminPermission($admin, 'customer.view');

    $orgB = Organization::factory()->create();
    $foreign = Customer::factory()->create(['organization_id' => $orgB->id]);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->getJson("/api/v1/customers/{$foreign->id}")
        ->assertNotFound()
        ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
});

it('updates a customer profile and status', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    grantCustomerAdminPermission($admin, 'customer.update');

    $customer = Customer::factory()->create(['organization_id' => $org->id]);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->patchJson("/api/v1/customers/{$customer->id}", [
            'name' => 'Renamed Customer',
            'status' => 'INACTIVE',
            'city' => 'Jakarta',
            'country_code' => 'ID',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renamed Customer')
        ->assertJsonPath('data.status', 'INACTIVE')
        ->assertJsonPath('data.country_code', 'ID');

    expect($customer->fresh()->status)->toBe(CustomerStatus::Inactive);
});

it('archives a customer on DELETE instead of hard deleting', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    grantCustomerAdminPermission($admin, 'customer.archive');

    $customer = Customer::factory()->create(['organization_id' => $org->id]);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->deleteJson("/api/v1/customers/{$customer->id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'ARCHIVED');

    $fresh = $customer->fresh();

    expect($fresh)->not->toBeNull();
    expect($fresh->status)->toBe(CustomerStatus::Archived);
    expect($fresh->archived_at)->not->toBeNull();
});

it('returns 404 when archiving a customer from another organization', function () {
    $orgA = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $orgA->id]);
    grantCustomerAdminPermission($admin, 'customer.archive');

    $orgB = Organization::factory()->create();
    $foreign = Customer::factory()->create(['organization_id' => $orgB->id]);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->deleteJson("/api/v1/customers/{$foreign->id}")
        ->assertNotFound();
});

it('adds a contact to a customer and lists it', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    grantCustomerAdminPermission($admin, 'customer.update');
    grantCustomerAdminPermission($admin, 'customer.view');

    $customer = Customer::factory()->create(['organization_id' => $org->id]);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/customers/{$customer->id}/contacts", [
            'name' => 'Jane Doe',
            'email' => 'jane@acme.example',
            'phone' => '+65 9000 0001',
            'job_title' => 'Procurement',
            'is_primary' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Jane Doe')
        ->assertJsonPath('data.email', 'jane@acme.example')
        ->assertJsonPath('data.is_primary', true);

    $this->withToken($token)
        ->getJson("/api/v1/customers/{$customer->id}/contacts")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.name', 'Jane Doe');
});

it('keeps only one primary contact per customer', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id]);
    grantCustomerAdminPermission($admin, 'customer.update');
    grantCustomerAdminPermission($admin, 'customer.view');

    $customer = Customer::factory()->create(['organization_id' => $org->id]);

    $customer->contacts()->create([
        'organization_id' => $org->id,
        'name' => 'First Contact',
        'email' => 'first@acme.example',
        'is_primary' => true,
    ]);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/customers/{$customer->id}/contacts", [
            'name' => 'Second Contact',
            'email' => 'second@acme.example',
            'is_primary' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.is_primary', true);

    $primaries = $customer->contacts()->where('is_primary', true)->count();

    expect($primaries)->toBe(1);
    expect($customer->contacts()->where('email', 'second@acme.example')->first()->is_primary)->toBeTrue();
});

it('returns 404 when listing contacts of a foreign customer', function () {
    $orgA = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $orgA->id]);
    grantCustomerAdminPermission($admin, 'customer.view');

    $orgB = Organization::factory()->create();
    $foreign = Customer::factory()->create(['organization_id' => $orgB->id]);

    $token = $admin->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->getJson("/api/v1/customers/{$foreign->id}/contacts")
        ->assertNotFound()
        ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
});
