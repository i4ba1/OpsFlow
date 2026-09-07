<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'customer_id' => Customer::factory(),
            'project_code' => 'PRJ-'.strtoupper(Str::random(8)),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->paragraph(),
            'status' => ProjectStatus::Draft,
            'starts_on' => null,
            'ends_on' => null,
            'manager_id' => null,
            'sla_policy_id' => null,
            'archived_at' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => ProjectStatus::Active,
        ]);
    }
}
