<?php

namespace Database\Factories;

use App\Models\BusinessCalendar;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessCalendar>
 */
class BusinessCalendarFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->unique()->words(2, true).' Calendar',
            'timezone' => 'Asia/Singapore',
            'description' => null,
        ];
    }

    /**
     * The default working week (PRD §13.3): Mon-Fri 09:00-18:00.
     */
    public function weekdays(): static
    {
        return $this->afterCreating(function (BusinessCalendar $calendar): void {
            foreach ([1, 2, 3, 4, 5] as $day) {
                $calendar->hours()->create([
                    'day_of_week' => $day,
                    'opens_at' => '09:00:00',
                    'closes_at' => '18:00:00',
                ]);
            }
        });
    }
}
