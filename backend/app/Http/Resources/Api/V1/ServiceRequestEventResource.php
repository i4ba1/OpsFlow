<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\ServiceRequestEventType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A single entry of the unified service request timeline (PRD §66).
 *
 * @property string $id
 * @property ServiceRequestEventType $type
 * @property string|null $actor_id
 * @property string|null $from_value
 * @property string|null $to_value
 * @property string|null $note
 * @property array<string, mixed>|null $metadata
 * @property User|null $actor
 * @property string|null $created_at
 */
class ServiceRequestEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'actor' => $this->actor_id !== null
                ? ['id' => $this->actor_id, 'name' => $this->actor?->name]
                : null,
            'occurred_at' => $this->created_at,
            'data' => [
                'from' => $this->from_value,
                'to' => $this->to_value,
            ],
            'note' => $this->note,
            'metadata' => $this->metadata,
        ];
    }
}
