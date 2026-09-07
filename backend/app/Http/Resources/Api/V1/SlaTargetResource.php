<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $id
 * @property string $priority
 * @property int $first_response_minutes
 * @property int $resolution_minutes
 * @property int $warning_threshold_percent
 */
class SlaTargetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'priority' => $this->priority,
            'first_response_minutes' => $this->first_response_minutes,
            'resolution_minutes' => $this->resolution_minutes,
            'warning_threshold_percent' => $this->warning_threshold_percent,
        ];
    }
}
