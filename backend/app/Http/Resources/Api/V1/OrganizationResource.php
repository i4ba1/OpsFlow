<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $legal_name
 * @property string $country_code
 * @property string $timezone
 * @property string $currency
 * @property string $status
 */
class OrganizationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'legal_name' => $this->legal_name,
            'country_code' => $this->country_code,
            'timezone' => $this->timezone,
            'currency' => $this->currency,
            'status' => $this->status,
        ];
    }
}
