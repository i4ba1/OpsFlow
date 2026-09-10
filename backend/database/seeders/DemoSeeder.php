<?php

namespace Database\Seeders;

use App\Domain\SLA\BusinessCalendar as SlaBusinessCalendar;
use App\Domain\SLA\SlaCalculator;
use App\Enums\ServiceRequestPriority;
use App\Enums\ServiceRequestStatus;
use App\Models\Approval;
use App\Models\BusinessCalendar;
use App\Models\BusinessCalendarHour;
use App\Models\Comment;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Role;
use App\Models\ServiceRequest;
use App\Models\SlaPolicy;
use App\Models\User;
use App\Support\RequestNumberGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Phase 10 demo dataset (PRD §56). Builds the Merlion Digital Services tenant
 * with the demo credential set, three showcase customers/projects, an SLA
 * policy on the Singapore business calendar, and ~120 service requests spread
 * across statuses, priorities and SLA outcomes so the dashboard is visually
 * meaningful on first boot.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::query()->firstOrCreate(
            ['slug' => 'merlion-digital'],
            [
                'name' => 'Merlion Digital Services Pte. Ltd.',
                'legal_name' => 'Merlion Digital Services Pte. Ltd.',
                'registration_number' => '202012345M',
                'country_code' => 'SG',
                'timezone' => 'Asia/Singapore',
                'currency' => 'SGD',
                'status' => 'ACTIVE',
            ],
        );

        $this->seedCalendarAndPolicy($org);

        $roles = $this->seedRoles($org);

        $admin = $this->user($org, 'admin@demo.opsflow.local', 'Admin User', $roles['admin']);
        $manager = $this->user($org, 'manager@demo.opsflow.local', 'Sarah Lim', $roles['manager']);
        $operator = $this->user($org, 'operator@demo.opsflow.local', 'Wei Ming Tan', $roles['operator']);
        $finance = $this->user($org, 'finance@demo.opsflow.local', 'Priya Nair', $roles['finance']);
        $customerUser = $this->user($org, 'customer@demo.opsflow.local', 'Daniel Koh', $roles['customer']);

        $this->seedCustomersAndProjects($org, $customerUser, $manager);

        $this->seedRequests($org, $manager, $operator, $customerUser);
        $this->seedApprovals($org, $manager, $operator, $customerUser);
        $this->seedInvoices($org, $finance);
    }

    private function seedCalendarAndPolicy(Organization $org): void
    {
        $calendar = BusinessCalendar::query()->firstOrCreate(
            ['organization_id' => $org->id, 'name' => 'Singapore Business Hours'],
            ['description' => 'Mon–Fri 09:00–18:00 Asia/Singapore', 'timezone' => 'Asia/Singapore'],
        );

        if ($calendar->hours()->count() === 0) {
            foreach ([1, 2, 3, 4, 5] as $day) {
                BusinessCalendarHour::query()->create([
                    'business_calendar_id' => $calendar->id,
                    'day_of_week' => $day,
                    'opens_at' => '09:00:00',
                    'closes_at' => '18:00:00',
                ]);
            }
        }

        $policy = SlaPolicy::query()->firstOrCreate(
            ['organization_id' => $org->id, 'name' => 'Standard Support SLA'],
            [
                'description' => 'Default SLA for managed support (PRD §13.2 targets)',
                'timezone' => 'Asia/Singapore',
                'business_calendar_id' => $calendar->id,
                'active' => true,
            ],
        );

        if ($policy->targets()->count() === 0) {
            $targets = [
                ServiceRequestPriority::Critical->value => [15, 240, 75],
                ServiceRequestPriority::High->value => [60, 480, 75],
                ServiceRequestPriority::Medium->value => [240, 1440, 80],
                ServiceRequestPriority::Low->value => [480, 4320, 80],
            ];

            foreach ($targets as $priority => [$firstResponse, $resolution, $warning]) {
                $policy->targets()->create([
                    'priority' => $priority,
                    'first_response_minutes' => $firstResponse,
                    'resolution_minutes' => $resolution,
                    'warning_threshold_percent' => $warning,
                ]);
            }
        }
    }

    /**
     * @return array<string, Role>
     */
    private function seedRoles(Organization $org): array
    {
        $this->callWith(OrganizationRoleSeeder::class, ['organization' => $org]);

        return $org->roles()->get()->keyBy('slug')->all();
    }

    private function user(Organization $org, string $email, string $name, Role $role): User
    {
        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'organization_id' => $org->id,
                'name' => $name,
                'status' => 'ACTIVE',
                'timezone' => 'Asia/Singapore',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        if (! $user->roles()->whereKey($role->id)->exists()) {
            $user->roles()->attach($role->id);
        }

        return $user;
    }

    private function seedCustomersAndProjects(Organization $org, User $customerUser, User $manager): void
    {
        $customers = [
            ['Orchid Retail Pte. Ltd.', 'CUS-ORCHID01'],
            ['Lion City Logistics Pte. Ltd.', 'CUS-LION01'],
            ['HarbourTech Solutions Pte. Ltd.', 'CUS-HARB01'],
        ];

        $projects = [
            ['Managed Application Support', 'PRJ-MAS-2026', 'Orchid Retail Pte. Ltd.'],
            ['Regional Reporting Platform', 'PRJ-RRP-2026', 'Lion City Logistics Pte. Ltd.'],
            ['Customer Portal Revamp', 'PRJ-CPR-2026', 'HarbourTech Solutions Pte. Ltd.'],
        ];

        $customerModels = [];

        foreach ($customers as [$name, $code]) {
            $customer = Customer::query()->firstOrCreate(
                ['organization_id' => $org->id, 'customer_code' => $code],
                [
                    'name' => $name,
                    'legal_name' => $name,
                    'email' => Str::slug($name, '.').'@example.com',
                    'country_code' => 'SG',
                    'status' => 'ACTIVE',
                    'created_by' => $manager->id,
                ],
            );

            CustomerContact::query()->firstOrCreate(
                ['organization_id' => $org->id, 'customer_id' => $customer->id, 'email' => $customerUser->email],
                [
                    'user_id' => $customerUser->id,
                    'name' => $customerUser->name,
                    'is_primary' => true,
                ],
            );

            $customerModels[$name] = $customer;
        }

        foreach ($projects as [$name, $code, $customerName]) {
            Project::query()->firstOrCreate(
                ['organization_id' => $org->id, 'project_code' => $code],
                [
                    'customer_id' => $customerModels[$customerName]->id,
                    'name' => $name,
                    'status' => 'ACTIVE',
                    'manager_id' => $manager->id,
                ],
            );
        }
    }

    private function seedRequests(Organization $org, User $manager, User $operator, User $requester): void
    {
        if (ServiceRequest::query()->where('organization_id', $org->id)->count() > 30) {
            return;
        }

        $policy = SlaPolicy::query()->where('organization_id', $org->id)->firstOrFail();
        $calculator = app(SlaCalculator::class);
        $calendar = SlaBusinessCalendar::fromModel($policy->businessCalendar);
        $numbers = app(RequestNumberGenerator::class);
        $customers = Customer::query()->where('organization_id', $org->id)->get();
        $projects = Project::query()->where('organization_id', $org->id)->get();

        $statuses = [
            ServiceRequestStatus::New,
            ServiceRequestStatus::Triaged,
            ServiceRequestStatus::InProgress,
            ServiceRequestStatus::WaitingCustomer,
            ServiceRequestStatus::Escalated,
            ServiceRequestStatus::Resolved,
            ServiceRequestStatus::Closed,
        ];

        $titles = [
            'Database timeout in reporting service',
            'Intermittent API timeout during checkout',
            'SSL certificate about to expire',
            'Monthly billing export fails on large accounts',
            'Mobile app push notifications not delivered',
            'Onboarding email template renders incorrectly',
            'Nightly backup job reports failure',
            'Slow dashboard queries on the finance module',
            'Duplicate invoices created for one order',
            'Portal login fails after password reset',
            'Warehouse label printer offline',
            'CRM sync dropping updated records',
            'New hire cannot access shared mailbox',
            'Checkout page shows stale inventory counts',
            'Webhook delivery delays to partner API',
        ];

        for ($i = 0; $i < 120; $i++) {
            $status = $statuses[array_rand($statuses)];
            $priority = ServiceRequestPriority::cases()[array_rand(ServiceRequestPriority::cases())];
            $customer = $customers->random();
            $created = now()->subDays(random_int(0, 45))->subHours(random_int(0, 12));

            $target = $policy->targets()->where('priority', $priority->value)->firstOrFail();

            $request = ServiceRequest::query()->create([
                'organization_id' => $org->id,
                'request_number' => $numbers->next($org->id),
                'customer_id' => $customer->id,
                'project_id' => $projects->random()->id,
                'requested_by' => $requester->id,
                'assigned_to' => $operator->id,
                'title' => $titles[array_rand($titles)].($i > 20 ? ' ('.$i.')' : ''),
                'description' => fake()->paragraph(3),
                'priority' => $priority,
                'status' => $status,
                'sla_policy_id' => $policy->id,
                'version' => 1,
            ]);

            $request->forceFill(['created_at' => $created])->save();

            $due = $calculator->dueAt(
                CarbonImmutable::make($created),
                (int) $target->resolution_minutes,
                $calendar,
                $policy->timezone,
            );

            $request->forceFill(['resolution_due_at' => $due])->save();

            $request->events()->create([
                'organization_id' => $org->id,
                'type' => 'STATUS_CHANGED',
                'actor_id' => $manager->id,
                'from_value' => null,
                'to_value' => $status->value,
                'note' => 'Seeded demo request',
            ]);

            if ($status === ServiceRequestStatus::Resolved || $status === ServiceRequestStatus::Closed) {
                $resolvedAt = $created->copy()->addHours(random_int(1, 40));

                $request->forceFill([
                    'resolved_at' => $resolvedAt,
                    'status' => $status,
                ])->save();

                if ($status === ServiceRequestStatus::Closed) {
                    $request->forceFill(['closed_at' => $resolvedAt->copy()->addDay()])->save();
                }
            }

            Comment::query()->create([
                'organization_id' => $org->id,
                'service_request_id' => $request->id,
                'author_id' => $operator->id,
                'body' => fake()->sentence(12),
                'visibility' => 'PUBLIC',
            ]);
        }
    }

    private function seedApprovals(Organization $org, User $manager, User $operator, User $customerUser): void
    {
        $requests = ServiceRequest::query()
            ->where('organization_id', $org->id)
            ->whereIn('status', [ServiceRequestStatus::InProgress->value, ServiceRequestStatus::WaitingCustomer->value])
            ->limit(6)
            ->get();

        foreach ($requests as $request) {
            Approval::query()->create([
                'organization_id' => $org->id,
                'service_request_id' => $request->id,
                'requested_by' => $operator->id,
                'assigned_to' => $customerUser->id,
                'type' => 'ACCEPTANCE',
                'title' => 'Accept resolution of '.$request->request_number,
                'description' => 'Please confirm the proposed resolution is acceptable.',
                'status' => 'PENDING',
                'due_at' => now()->addDays(3),
            ]);
        }
    }

    private function seedInvoices(Organization $org, User $finance): void
    {
        if (Invoice::query()->where('organization_id', $org->id)->exists()) {
            return;
        }

        $customers = Customer::query()->where('organization_id', $org->id)->get();

        foreach ($customers as $index => $customer) {
            $invoice = Invoice::query()->create([
                'organization_id' => $org->id,
                'invoice_number' => sprintf('INV-%s-%06d', now()->year, $index + 1),
                'customer_id' => $customer->id,
                'currency' => 'SGD',
                'status' => $index === 0 ? 'PAID' : ($index === 1 ? 'ISSUED' : 'OVERDUE'),
                'subtotal' => '0.00',
                'tax_amount' => '0.00',
                'total_amount' => '0.00',
                'issued_at' => now()->subDays($index * 15),
                'due_at' => now()->addDays(14 - $index * 20)->toDateString(),
                'paid_at' => $index === 0 ? now()->subDays(10) : null,
                'created_by' => $finance->id,
            ]);

            $items = [
                ['Managed support retainer (Mar)', 1, 1500],
                ['On-site engineering day', $index + 1, 450],
                ['Infrastructure monitoring', 1, 120],
            ];

            $subtotal = 0.0;

            foreach ($items as $order => [$description, $qty, $price]) {
                $amount = round($qty * $price, 2);
                $subtotal += $amount;

                InvoiceItem::query()->create([
                    'invoice_id' => $invoice->id,
                    'description' => $description,
                    'quantity' => (string) $qty,
                    'unit_price' => number_format($price, 2, '.', ''),
                    'amount' => number_format($amount, 2, '.', ''),
                    'sort_order' => $order,
                ]);
            }

            $invoice->forceFill([
                'subtotal' => number_format($subtotal, 2, '.', ''),
                'total_amount' => number_format($subtotal, 2, '.', ''),
            ])->save();
        }
    }
}
