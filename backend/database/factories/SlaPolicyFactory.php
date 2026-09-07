<?php

namespace Database\Factories;

use App\Enums\ServiceRequestPriority;
use App\Models\Organization;
use App\Models\SlaPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SlaPolicy>
 */
class SlaPolicyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->unique()->words(2, true).' SLA',
            'description' => null,
            'timezone' => 'Asia/Singapore',
            'business_calendar_id' => null,
            'active' => true,
        ];
    }

    /**
     * Attach default targets for every priority (PRD §13.2).
     */
    public function withTargets(): static
    {
        return $this->afterCreating(function (SlaPolicy $policy): void {
            $defaults = [
                ServiceRequestPriority::Critical->value => ['first_response_minutes' => 15, 'resolution_minutes' => 240, 'warning_threshold_percent' => 75],
                ServiceRequestPriority::High->value => ['first_response_minutes' => 60, 'resolution_minutes' => 480, 'warning_threshold_percent' => 75],
                ServiceRequestPriority::Medium->value => ['first_response_minutes' => 240, 'resolution_minutes' => 1440, 'warning_threshold_percent' => 80],
                ServiceRequestPriority::Low->value => ['first_response_minutes' => 480, 'resolution_minutes' => 4320, 'warning_threshold_percent' => 80],
            ];

            foreach ($defaults as $priority => $target) {
                $policy->targets()->create([
                    'priority' => $priority,
                    'first_response_minutes' => $target['first_response_minutes'],
                    'resolution_minutes' => $target['resolution_minutes'],
                    'warning_threshold_percent' => $target['warning_threshold_percent'],
                ]);
            }
        });
    }
}
