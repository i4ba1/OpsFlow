<?php

namespace Database\Factories;

use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Models\Approval;
use App\Models\Organization;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Approval>
 */
class ApprovalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'service_request_id' => ServiceRequest::factory(),
            'requested_by' => User::factory(),
            'assigned_to' => User::factory(),
            'type' => ApprovalType::Acceptance,
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'status' => ApprovalStatus::Pending,
            'due_at' => null,
            'responded_at' => null,
            'response_note' => null,
        ];
    }
}
