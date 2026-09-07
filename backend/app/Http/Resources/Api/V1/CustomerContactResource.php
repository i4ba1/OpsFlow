<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $id
 * @property string $customer_id
 * @property string|null $user_id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $job_title
 * @property bool $is_primary
 * @property string|null $created_at
 */
class CustomerContactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'job_title' => $this->job_title,
            'is_primary' => $this->is_primary,
            'created_at' => $this->created_at,
        ];
    }
}
