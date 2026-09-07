<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $user_id
 * @property string $type
 * @property array<string, mixed>|null $data
 * @property string|null $actor_id
 * @property Carbon|null $read_at
 * @property User|null $actor
 * @property string|null $resource_type
 * @property string|null $resource_id
 * @property Carbon|null $created_at
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'data' => $this->data,
            'read_at' => $this->read_at,
            'read' => $this->read_at !== null,
            'actor' => $this->actor_id !== null
                ? ['id' => $this->actor_id, 'name' => $this->actor?->name]
                : null,
            'resource' => $this->resource_type !== null
                ? ['type' => $this->resource_type, 'id' => $this->resource_id]
                : null,
            'created_at' => $this->created_at,
        ];
    }
}
