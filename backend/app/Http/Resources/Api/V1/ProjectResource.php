<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\ProjectStatus;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $customer_id
 * @property string $project_code
 * @property string $name
 * @property string|null $description
 * @property ProjectStatus $status
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property Customer $customer
 * @property User $manager
 * @property string|null $archived_at
 * @property string|null $created_at
 */
class ProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_code' => $this->project_code,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'customer_id' => $this->customer_id,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'manager' => new UserResource($this->whenLoaded('manager')),
            'archived_at' => $this->archived_at,
            'created_at' => $this->created_at,
        ];
    }
}
