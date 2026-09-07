<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    public function definition(): array
    {
        $slug = $this->faker->unique()->slug(2);

        return [
            'organization_id' => Organization::factory(),
            'name' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'description' => $this->faker->sentence(),
            'is_system' => false,
        ];
    }
}
