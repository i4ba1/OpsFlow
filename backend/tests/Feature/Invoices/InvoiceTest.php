<?php

use App\Enums\InvoiceStatus;
use App\Jobs\MarkOverdueInvoices;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Support\Notifications\NotificationType;

/**
 * Grant the given user a single permission by attaching it through a fresh
 * role, mirroring how permission middleware checks real DB rows.
 */
function grantInvoicePermission(User $user, string $permission): void
{
    $permissionModel = Permission::firstOrCreate(
        ['name' => $permission],
        ['group' => explode('.', $permission)[0], 'description' => ucfirst($permission)],
    );

    $role = Role::query()->firstOrCreate(
        ['organization_id' => $user->organization_id, 'slug' => 'invoice-'.$permission],
        ['name' => 'Invoice '.$permission, 'description' => null],
    );
    $role->permissions()->syncWithoutDetaching($permissionModel->name);
    $user->roles()->syncWithoutDetaching($role->id);
}

function makeInvoiceManager(Organization $org): User
{
    $user = User::factory()->create(['organization_id' => $org->id]);

    foreach (['invoice.view', 'invoice.create', 'invoice.update', 'invoice.issue', 'invoice.mark_paid', 'invoice.void'] as $permission) {
        grantInvoicePermission($user, $permission);
    }

    return $user;
}

function invoiceCustomer(Organization $org, ?User $createdBy = null): Customer
{
    return Customer::factory()->create([
        'organization_id' => $org->id,
        'created_by' => $createdBy?->id,
    ]);
}

function invoiceItemsPayload(): array
{
    return [
        ['description' => 'Managed services - January', 'quantity' => 2, 'unit_price' => 19.99],
        ['description' => 'Onboarding fee', 'quantity' => 0.5, 'unit_price' => 100.00],
    ];
}

it('creates a draft invoice with computed line item and invoice totals', function () {
    $org = Organization::factory()->create();
    $manager = makeInvoiceManager($org);
    $customer = invoiceCustomer($org, $manager);
    $project = Project::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
    ]);

    $token = $manager->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/invoices', [
            'customer_id' => $customer->id,
            'project_id' => $project->id,
            'due_at' => '2026-10-31',
            'notes' => 'Payable within 30 days.',
            'currency' => 'SGD',
            'items' => invoiceItemsPayload(),
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'DRAFT')
        ->assertJsonPath('data.currency', 'SGD')
        // 2 x 19.99 = 39.98 plus 0.5 x 100.00 = 50.00 → subtotal 89.98.
        ->assertJsonPath('data.subtotal', '89.98')
        ->assertJsonPath('data.tax_amount', '0.00')
        ->assertJsonPath('data.total_amount', '89.98')
        ->assertJsonPath('data.due_at', '2026-10-31')
        ->assertJsonPath('data.notes', 'Payable within 30 days.')
        ->assertJsonCount(2, 'data.items');

    $invoice = Invoice::query()->firstOrFail();

    expect($invoice->organization_id)->toBe($org->id);
    expect($invoice->customer_id)->toBe($customer->id);
    expect($invoice->project_id)->toBe($project->id);
    expect($invoice->status)->toBe(InvoiceStatus::Draft);
    expect($invoice->created_by)->toBe($manager->id);
    expect($invoice->issued_at)->toBeNull();
    expect($invoice->paid_at)->toBeNull();
    expect($invoice->subtotal)->toBe('89.98');
    expect($invoice->tax_amount)->toBe('0.00');
    expect($invoice->total_amount)->toBe('89.98');

    $items = $invoice->items()->orderBy('sort_order')->get();

    expect($items)->toHaveCount(2);
    expect($items[0]->amount)->toBe('39.98');
    expect($items[0]->quantity)->toBe('2.00');
    expect($items[0]->unit_price)->toBe('19.99');
    expect($items[1]->amount)->toBe('50.00');
    expect($items[1]->quantity)->toBe('0.50');
    expect($items[1]->unit_price)->toBe('100.00');

    // Drafts have not been issued yet: no issued_at, no number sequence used.
    expect($invoice->invoice_number)->toBeString();
});

it('rejects creating an invoice for a customer outside the organization', function () {
    $org = Organization::factory()->create();
    $manager = makeInvoiceManager($org);

    $foreignOrg = Organization::factory()->create();
    $foreignCustomer = Customer::factory()->create(['organization_id' => $foreignOrg->id]);

    $token = $manager->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/invoices', [
            'customer_id' => $foreignCustomer->id,
            'items' => [
                ['description' => 'Item', 'quantity' => 1, 'unit_price' => 10],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');

    expect(Invoice::query()->count())->toBe(0);
});

it('rejects creating an invoice without line items', function () {
    $org = Organization::factory()->create();
    $manager = makeInvoiceManager($org);
    $customer = invoiceCustomer($org);

    $token = $manager->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/invoices', [
            'customer_id' => $customer->id,
            'items' => [],
        ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');

    expect(Invoice::query()->count())->toBe(0);
});

it('requires invoice.create permission to create an invoice', function () {
    $org = Organization::factory()->create();
    $viewer = User::factory()->create(['organization_id' => $org->id]);
    grantInvoicePermission($viewer, 'invoice.view');
    $customer = invoiceCustomer($org);

    $token = $viewer->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/invoices', [
            'customer_id' => $customer->id,
            'items' => [
                ['description' => 'Item', 'quantity' => 1, 'unit_price' => 10],
            ],
        ])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FORBIDDEN');

    expect(Invoice::query()->count())->toBe(0);
});

it('lists invoices paginated and filtered by status and search', function () {
    $org = Organization::factory()->create();
    $manager = makeInvoiceManager($org);
    $customer = invoiceCustomer($org);

    $issued = Invoice::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'invoice_number' => 'INV-'.now()->format('Y').'-000101',
        'subtotal' => '100.00',
        'total_amount' => '100.00',
    ]);
    Invoice::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'status' => InvoiceStatus::Draft,
        'invoice_number' => 'INV-'.now()->format('Y').'-000102',
        'subtotal' => '50.00',
        'total_amount' => '50.00',
    ]);

    // A foreign-org invoice must never leak into the list.
    $foreignOrg = Organization::factory()->create();
    Invoice::factory()->create([
        'organization_id' => $foreignOrg->id,
        'customer_id' => Customer::factory()->create(['organization_id' => $foreignOrg->id])->id,
        'status' => InvoiceStatus::Draft,
        'invoice_number' => 'INV-'.now()->format('Y').'-000999',
    ]);

    $token = $manager->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/invoices')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 2)
        ->assertJsonStructure([
            'data' => [['id', 'invoice_number', 'status', 'subtotal', 'total_amount']],
            'meta' => ['current_page', 'per_page', 'total', 'last_page', 'request_id'],
        ]);

    $this->withToken($token)
        ->getJson('/api/v1/invoices?status[]=ISSUED')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $issued->id);

    $this->withToken($token)
        ->getJson('/api/v1/invoices?search=000101')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $issued->id);
});

it('shows an invoice detail with items and string totals', function () {
    $org = Organization::factory()->create();
    $manager = makeInvoiceManager($org);
    $customer = invoiceCustomer($org);
    $invoice = Invoice::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'created_by' => $manager->id,
    ]);
    $invoice->items()->createMany([
        ['description' => 'Line one', 'quantity' => '1.00', 'unit_price' => '10.00', 'amount' => '10.00', 'sort_order' => 0],
        ['description' => 'Line two', 'quantity' => '3.00', 'unit_price' => '5.50', 'amount' => '16.50', 'sort_order' => 1],
    ]);
    $invoice->recalculateTotals();
    $invoice->save();

    $token = $manager->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->getJson("/api/v1/invoices/{$invoice->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $invoice->id)
        ->assertJsonPath('data.customer.id', $customer->id)
        ->assertJsonPath('data.subtotal', '26.50')
        ->assertJsonPath('data.tax_amount', '0.00')
        ->assertJsonPath('data.total_amount', '26.50')
        ->assertJsonPath('data.items.0.description', 'Line one')
        ->assertJsonPath('data.items.1.amount', '16.50')
        ->assertJsonCount(2, 'data.items');
});

it('returns 404 when showing a foreign-organization invoice', function () {
    $orgA = Organization::factory()->create();
    $managerA = makeInvoiceManager($orgA);

    $orgB = Organization::factory()->create();
    $foreign = Invoice::factory()->create([
        'organization_id' => $orgB->id,
        'customer_id' => Customer::factory()->create(['organization_id' => $orgB->id])->id,
    ]);

    $token = $managerA->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->getJson("/api/v1/invoices/{$foreign->id}")
        ->assertNotFound()
        ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
});

it('updates a draft invoice, replacing items and recomputing totals', function () {
    $org = Organization::factory()->create();
    $manager = makeInvoiceManager($org);
    $customer = invoiceCustomer($org);
    $invoice = Invoice::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'created_by' => $manager->id,
    ]);
    $invoice->items()->create([
        'description' => 'Original line',
        'quantity' => '1.00',
        'unit_price' => '10.00',
        'amount' => '10.00',
        'sort_order' => 0,
    ]);
    $invoice->recalculateTotals();
    $invoice->save();

    $token = $manager->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->patchJson("/api/v1/invoices/{$invoice->id}", [
            'notes' => 'Revised scope',
            'items' => [
                ['description' => 'Replacement line', 'quantity' => 3, 'unit_price' => 25.00],
                ['description' => 'Second line', 'quantity' => 1, 'unit_price' => 4.99],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.notes', 'Revised scope')
        ->assertJsonPath('data.subtotal', '79.99')
        ->assertJsonPath('data.total_amount', '79.99')
        ->assertJsonCount(2, 'data.items');

    $fresh = $invoice->fresh();

    expect($fresh->notes)->toBe('Revised scope');
    expect($fresh->subtotal)->toBe('79.99');
    expect($fresh->total_amount)->toBe('79.99');
    expect($fresh->items()->count())->toBe(2);
    expect($fresh->items()->where('description', 'Original line')->exists())->toBeFalse();
});

it('rejects updating an issued invoice with INVOICE_NOT_EDITABLE', function () {
    $org = Organization::factory()->create();
    $manager = makeInvoiceManager($org);
    $customer = invoiceCustomer($org);
    $invoice = Invoice::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'subtotal' => '100.00',
        'total_amount' => '100.00',
    ]);

    $token = $manager->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->patchJson("/api/v1/invoices/{$invoice->id}", [
            'notes' => 'Too late to change',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'INVOICE_NOT_EDITABLE');

    expect($invoice->fresh()->notes)->toBeNull();
});

it('returns 404 when patching a foreign-organization invoice', function () {
    $orgA = Organization::factory()->create();
    $managerA = makeInvoiceManager($orgA);

    $orgB = Organization::factory()->create();
    $foreign = Invoice::factory()->create([
        'organization_id' => $orgB->id,
        'customer_id' => Customer::factory()->create(['organization_id' => $orgB->id])->id,
    ]);

    $token = $managerA->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->patchJson("/api/v1/invoices/{$foreign->id}", ['notes' => 'Nope'])
        ->assertNotFound()
        ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
});

it('requires invoice.update permission to update an invoice', function () {
    $org = Organization::factory()->create();
    $viewer = User::factory()->create(['organization_id' => $org->id]);
    grantInvoicePermission($viewer, 'invoice.view');
    $customer = invoiceCustomer($org);
    $invoice = Invoice::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
    ]);

    $token = $viewer->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->patchJson("/api/v1/invoices/{$invoice->id}", ['notes' => 'Nope'])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FORBIDDEN');
});

it('issues a draft invoice allocating an INV number and stamping dates', function () {
    $org = Organization::factory()->create();
    $manager = makeInvoiceManager($org);
    $customer = invoiceCustomer($org);
    $invoice = Invoice::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'created_by' => $manager->id,
        'invoice_number' => 'INV-PLACEHOLDER',
        'due_at' => null,
    ]);
    $invoice->items()->createMany([
        ['description' => 'Setup', 'quantity' => '1.00', 'unit_price' => '200.00', 'amount' => '200.00', 'sort_order' => 0],
        ['description' => 'License', 'quantity' => '12.00', 'unit_price' => '15.50', 'amount' => '186.00', 'sort_order' => 1],
    ]);
    $invoice->recalculateTotals();
    $invoice->save();

    $year = now()->format('Y');

    $token = $manager->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/invoices/{$invoice->id}/issue")
        ->assertOk()
        ->assertJsonPath('data.status', 'ISSUED')
        ->assertJsonPath('data.total_amount', '386.00');

    $fresh = $invoice->fresh();

    expect($fresh->status)->toBe(InvoiceStatus::Issued);
    expect($fresh->invoice_number)->toStartWith("INV-{$year}-");
    expect($fresh->invoice_number)->not->toBe('INV-PLACEHOLDER');
    expect($fresh->issued_at)->not->toBeNull();
    // No due date on the draft → defaults to 30 days after issue.
    expect($fresh->due_at?->toDateString())->toBe(now()->addDays(30)->toDateString());
    expect($fresh->paid_at)->toBeNull();

    // Draft creates leave no number sequence row; issuing allocates one.
    $sequence = DB::table('invoice_sequences')
        ->where('organization_id', $org->id)
        ->where('year', (int) $year)
        ->first();

    expect($sequence)->not->toBeNull();
    expect((int) $sequence->last_value)->toBe(1);

    $issuedNotification = Notification::query()->where('type', NotificationType::INVOICE_ISSUED)->first();

    expect($issuedNotification)->not->toBeNull();
    expect($issuedNotification->user_id)->toBe($manager->id);
    expect($issuedNotification->actor_id)->toBe($manager->id);
    expect($issuedNotification->resource_type)->toBe('invoice');
    expect($issuedNotification->resource_id)->toBe($fresh->id);
});

it('rejects issuing an already issued invoice', function () {
    $org = Organization::factory()->create();
    $manager = makeInvoiceManager($org);
    $customer = invoiceCustomer($org);
    $invoice = Invoice::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'due_at' => now()->addDays(30),
    ]);

    $token = $manager->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/invoices/{$invoice->id}/issue")
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'INVOICE_NOT_EDITABLE');

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Issued);
});

it('issues sequential invoice numbers across two invoices of one organization', function () {
    $org = Organization::factory()->create();
    $manager = makeInvoiceManager($org);
    $customer = invoiceCustomer($org);

    $first = Invoice::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
    ]);
    $second = Invoice::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
    ]);

    $token = $manager->createToken('spa')->plainTextToken;

    $this->withToken($token)->postJson("/api/v1/invoices/{$first->id}/issue")->assertOk();

    auth()->forgetGuards();

    $this->withToken($token)->postJson("/api/v1/invoices/{$second->id}/issue")->assertOk();

    expect($first->fresh()->invoice_number)->toBe('INV-'.now()->format('Y').'-000001');
    expect($second->fresh()->invoice_number)->toBe('INV-'.now()->format('Y').'-000002');
});

it('marks an issued invoice as paid', function () {
    $org = Organization::factory()->create();
    $manager = makeInvoiceManager($org);
    $customer = invoiceCustomer($org);
    $invoice = Invoice::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now()->subDay(),
        'due_at' => now()->addDays(29),
    ]);

    $token = $manager->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/invoices/{$invoice->id}/mark-paid")
        ->assertOk()
        ->assertJsonPath('data.status', 'PAID');

    $fresh = $invoice->fresh();

    expect($fresh->status)->toBe(InvoiceStatus::Paid);
    expect($fresh->paid_at)->not->toBeNull();
});

it('rejects marking an already paid invoice paid with INVOICE_ALREADY_PAID', function () {
    $org = Organization::factory()->create();
    $manager = makeInvoiceManager($org);
    $customer = invoiceCustomer($org);
    $invoice = Invoice::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'status' => InvoiceStatus::Paid,
        'issued_at' => now()->subMonth(),
        'paid_at' => now()->subWeek(),
    ]);

    $token = $manager->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/invoices/{$invoice->id}/mark-paid")
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'INVOICE_ALREADY_PAID');

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

it('marks an overdue invoice as paid', function () {
    $org = Organization::factory()->create();
    $manager = makeInvoiceManager($org);
    $customer = invoiceCustomer($org);
    $invoice = Invoice::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'status' => InvoiceStatus::Overdue,
        'issued_at' => now()->subMonths(2),
        'due_at' => now()->subMonth(),
    ]);

    $token = $manager->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/invoices/{$invoice->id}/mark-paid")
        ->assertOk()
        ->assertJsonPath('data.status', 'PAID');
});

it('voids an issued invoice but not a paid one', function () {
    $org = Organization::factory()->create();
    $manager = makeInvoiceManager($org);
    $customer = invoiceCustomer($org);

    $issued = Invoice::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
    ]);
    $paid = Invoice::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'status' => InvoiceStatus::Paid,
        'issued_at' => now()->subMonth(),
        'paid_at' => now()->subWeek(),
    ]);

    $token = $manager->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/invoices/{$issued->id}/void")
        ->assertOk()
        ->assertJsonPath('data.status', 'VOID');

    expect($issued->fresh()->status)->toBe(InvoiceStatus::Void);

    $this->withToken($token)
        ->postJson("/api/v1/invoices/{$paid->id}/void")
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'INVOICE_ALREADY_PAID');

    expect($paid->fresh()->status)->toBe(InvoiceStatus::Paid);
});

it('returns 404 when issuing a foreign-organization invoice', function () {
    $orgA = Organization::factory()->create();
    $managerA = makeInvoiceManager($orgA);

    $orgB = Organization::factory()->create();
    $foreign = Invoice::factory()->create([
        'organization_id' => $orgB->id,
        'customer_id' => Customer::factory()->create(['organization_id' => $orgB->id])->id,
        'status' => InvoiceStatus::Draft,
    ]);

    $token = $managerA->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/invoices/{$foreign->id}/issue")
        ->assertNotFound()
        ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');

    expect($foreign->fresh()->status)->toBe(InvoiceStatus::Draft);
});

it('requires invoice.view permission to show an invoice', function () {
    $org = Organization::factory()->create();
    $customer = invoiceCustomer($org);
    $invoice = Invoice::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
    ]);
    $outsider = User::factory()->create(['organization_id' => $org->id]);

    $token = $outsider->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->getJson("/api/v1/invoices/{$invoice->id}")
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FORBIDDEN');
});

it('marks issued invoices past their due date overdue exactly once', function () {
    $org = Organization::factory()->create();
    $manager = makeInvoiceManager($org);
    $customer = invoiceCustomer($org);

    $overdue = Invoice::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now()->subMonths(2),
        'due_at' => now()->subDay(),
    ]);
    $dueToday = Invoice::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now()->subWeek(),
        'due_at' => now()->toDateString(),
    ]);
    $dueLater = Invoice::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'due_at' => now()->addDays(10),
    ]);
    $alreadyOverdue = Invoice::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'status' => InvoiceStatus::Overdue,
        'issued_at' => now()->subMonths(3),
        'due_at' => now()->subMonth(),
    ]);
    $draft = Invoice::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'status' => InvoiceStatus::Draft,
        'due_at' => now()->subDay(),
    ]);

    (new MarkOverdueInvoices)->handle();

    expect($overdue->fresh()->status)->toBe(InvoiceStatus::Overdue);
    expect($dueToday->fresh()->status)->toBe(InvoiceStatus::Issued);
    expect($dueLater->fresh()->status)->toBe(InvoiceStatus::Issued);
    expect($alreadyOverdue->fresh()->status)->toBe(InvoiceStatus::Overdue);
    expect($draft->fresh()->status)->toBe(InvoiceStatus::Draft);

    $overdueNotifications = Notification::query()
        ->where('type', NotificationType::INVOICE_OVERDUE)
        ->count();

    expect($overdueNotifications)->toBe(1);

    // A second run is idempotent: no ISSUED overdue rows are left to touch.
    (new MarkOverdueInvoices)->handle();

    expect(Invoice::query()->where('status', InvoiceStatus::Overdue)->count())->toBe(2);
    expect(Notification::query()->where('type', NotificationType::INVOICE_OVERDUE)->count())->toBe(1);
});
