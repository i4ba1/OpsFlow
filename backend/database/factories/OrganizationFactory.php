<?php

namespace Database\Factories;

use App\Enums\OrganizationStatus;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    public function definition(): array
    {
        $name = $this->faker->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'legal_name' => $name,
            'registration_number' => $this->faker->numerify('##########'),
            'country_code' => 'SG',
            'timezone' => 'Asia/Singapore',
            'currency' => 'SGD',
            'logo_path' => null,
            'status' => OrganizationStatus::Active,
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn () => [
            'status' => OrganizationStatus::Suspended,
        ]);
    }
}
