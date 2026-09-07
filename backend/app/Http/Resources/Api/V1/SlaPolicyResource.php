<?php

namespace App\Http\Resources\Api\V1;

use App\Models\SlaTarget;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $name
 * @property string|null $description
 * @property string $timezone
 * @property string|null $business_calendar_id
 * @property bool $active
 * @property Collection<int, SlaTarget> $targets
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SlaPolicyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'timezone' => $this->timezone,
            'business_calendar_id' => $this->business_calendar_id,
            'active' => $this->active,
            'targets' => SlaTargetResource::collection($this->whenLoaded('targets')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
