<?php

namespace App\Http\Requests\SLA;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSlaPolicyRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $organizationId = $this->user()->organization_id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'timezone' => ['sometimes', 'string', 'timezone'],
            'active' => ['sometimes', 'boolean'],
            'business_calendar_id' => [
                'nullable',
                'uuid',
                Rule::exists('business_calendars', 'id')
                    ->where('organization_id', $organizationId),
            ],
            'targets' => ['sometimes', 'array'],
            'targets.*.priority' => ['sometimes', 'string', 'max:20'],
            'targets.*.first_response_minutes' => ['required_with:targets.*.priority', 'integer', 'min:1'],
            'targets.*.resolution_minutes' => ['required_with:targets.*.priority', 'integer', 'min:1'],
            'targets.*.warning_threshold_percent' => ['nullable', 'integer', 'between:1,100'],
        ];
    }

    public function messages(): array
    {
        return [
            'business_calendar_id.exists' => 'The selected business calendar is invalid.',
        ];
    }
}
