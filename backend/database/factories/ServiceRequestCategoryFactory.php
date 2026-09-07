<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\ServiceRequestCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceRequestCategory>
 */
class ServiceRequestCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => ucfirst($this->faker->unique()->word()),
            'color' => $this->faker->hexColor(),
        ];
    }
}
