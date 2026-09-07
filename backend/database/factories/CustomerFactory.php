<?php

namespace Database\Factories;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'customer_code' => 'CUS-'.strtoupper(Str::random(8)),
            'name' => $this->faker->company(),
            'legal_name' => $this->faker->company(),
            'registration_number' => $this->faker->numerify('##########'),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'billing_email' => $this->faker->unique()->safeEmail(),
            'address_line_1' => $this->faker->streetAddress(),
            'address_line_2' => null,
            'city' => $this->faker->city(),
            'postal_code' => $this->faker->postcode(),
            'country_code' => 'SG',
            'status' => CustomerStatus::Active,
            'metadata' => null,
            'created_by' => null,
            'archived_at' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'status' => CustomerStatus::Inactive,
        ]);
    }
}
