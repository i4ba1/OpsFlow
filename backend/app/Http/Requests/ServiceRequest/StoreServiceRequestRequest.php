<?php

namespace App\Http\Requests\ServiceRequest;

use App\Enums\ServiceRequestPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceRequestRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $organizationId = $this->user()->organization_id;

        return [
            'customer_id' => [
                'required',
                'uuid',
                Rule::exists('customers', 'id')
                    ->where('organization_id', $organizationId),
            ],
            'project_id' => [
                'nullable',
                'uuid',
                Rule::exists('projects', 'id')
                    ->where('organization_id', $organizationId),
            ],
            'category_id' => [
                'nullable',
                'uuid',
                Rule::exists('service_request_categories', 'id')
                    ->where('organization_id', $organizationId),
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'priority' => ['nullable', Rule::enum(ServiceRequestPriority::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.exists' => 'The selected customer is invalid.',
            'project_id.exists' => 'The selected project is invalid.',
            'category_id.exists' => 'The selected category is invalid.',
        ];
    }
}
