<?php

namespace App\Http\Resources\Api\V1;

use App\Models\BusinessCalendarHoliday;
use App\Models\BusinessCalendarHour;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $name
 * @property string $timezone
 * @property string|null $description
 * @property Collection<int, BusinessCalendarHour> $hours
 * @property Collection<int, BusinessCalendarHoliday> $holidays
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class BusinessCalendarResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'timezone' => $this->timezone,
            'description' => $this->description,
            'hours' => BusinessCalendarHourResource::collection($this->whenLoaded('hours')),
            'holidays' => BusinessCalendarHolidayResource::collection($this->whenLoaded('holidays')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
