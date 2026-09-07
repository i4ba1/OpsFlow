<?php

namespace Database\Factories;

use App\Enums\CommentVisibility;
use App\Models\Comment;
use App\Models\Organization;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'service_request_id' => ServiceRequest::factory(),
            'author_id' => User::factory(),
            'body' => $this->faker->paragraph(),
            'visibility' => CommentVisibility::Public,
        ];
    }

    public function internal(): static
    {
        return $this->state(fn () => [
            'visibility' => CommentVisibility::Internal,
        ]);
    }
}
