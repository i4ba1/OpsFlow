<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\CustomerStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $id
 * @property string $customer_code
 * @property string $name
 * @property string|null $legal_name
 * @property string|null $registration_number
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $billing_email
 * @property string|null $address_line_1
 * @property string|null $address_line_2
 * @property string|null $city
 * @property string|null $postal_code
 * @property string $country_code
 * @property CustomerStatus $status
 * @property int $contacts_count
 * @property string|null $archived_at
 * @property string|null $created_at
 */
class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_code' => $this->customer_code,
            'name' => $this->name,
            'legal_name' => $this->legal_name,
            'registration_number' => $this->registration_number,
            'email' => $this->email,
            'phone' => $this->phone,
            'billing_email' => $this->billing_email,
            'address_line_1' => $this->address_line_1,
            'address_line_2' => $this->address_line_2,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'country_code' => $this->country_code,
            'status' => $this->status,
            'contacts_count' => $this->whenCounted('contacts'),
            'archived_at' => $this->archived_at,
            'created_at' => $this->created_at,
        ];
    }
}
