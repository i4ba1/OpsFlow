<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\Organization;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'service_request_id' => ServiceRequest::factory(),
            'comment_id' => null,
            'approval_id' => null,
            'uploaded_by' => User::factory(),
            'original_name' => $this->faker->word().'.pdf',
            'storage_disk' => 'local',
            'storage_path' => 'attachments/'.(string) Str::uuid7().'/file.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 2048,
            'checksum' => str_repeat('a', 64),
        ];
    }
}
