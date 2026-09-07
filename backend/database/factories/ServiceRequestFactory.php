<?php

namespace Database\Factories;

use App\Enums\ServiceRequestPriority;
use App\Enums\ServiceRequestStatus;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceRequest>
 */
class ServiceRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'request_number' => 'REQ-'.now()->format('Y').'-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'customer_id' => Customer::factory(),
            'project_id' => null,
            'requested_by' => User::factory(),
            'assigned_to' => null,
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'category_id' => null,
            'priority' => ServiceRequestPriority::Medium,
            'status' => ServiceRequestStatus::New,
            'sla_policy_id' => null,
            'first_response_due_at' => null,
            'resolution_due_at' => null,
            'first_responded_at' => null,
            'resolved_at' => null,
            'closed_at' => null,
            'version' => 1,
        ];
    }

    public function withStatus(ServiceRequestStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
            'resolved_at' => $status === ServiceRequestStatus::Resolved || $status === ServiceRequestStatus::Closed ? now() : null,
            'closed_at' => $status === ServiceRequestStatus::Closed ? now() : null,
        ]);
    }
}
