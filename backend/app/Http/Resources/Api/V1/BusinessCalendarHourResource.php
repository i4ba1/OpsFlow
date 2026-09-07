<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $id
 * @property int $day_of_week
 * @property string $opens_at
 * @property string $closes_at
 */
class BusinessCalendarHourResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'day_of_week' => $this->day_of_week,
            'opens_at' => substr((string) $this->opens_at, 0, 5),
            'closes_at' => substr((string) $this->closes_at, 0, 5),
        ];
    }
}
