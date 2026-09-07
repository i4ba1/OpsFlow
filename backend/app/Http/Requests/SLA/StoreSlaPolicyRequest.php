<?php

namespace App\Http\Requests\SLA;

use App\Enums\ServiceRequestPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSlaPolicyRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $organizationId = $this->user()->organization_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'timezone' => ['nullable', 'string', 'timezone'],
            'active' => ['nullable', 'boolean'],
            'business_calendar_id' => [
                'nullable',
                'uuid',
                Rule::exists('business_calendars', 'id')
                    ->where('organization_id', $organizationId),
            ],
            'targets' => ['required', 'array', 'min:1'],
            'targets.*.priority' => [
                'required',
                Rule::in(array_column(ServiceRequestPriority::cases(), 'value')),
            ],
            'targets.*.first_response_minutes' => ['required', 'integer', 'min:1'],
            'targets.*.resolution_minutes' => ['required', 'integer', 'min:1'],
            'targets.*.warning_threshold_percent' => ['nullable', 'integer', 'between:1,100'],
        ];
    }

    public function messages(): array
    {
        return [
            'targets.required' => 'An SLA policy needs at least one priority target.',
            'targets.min' => 'An SLA policy needs at least one priority target.',
            'business_calendar_id.exists' => 'The selected business calendar is invalid.',
        ];
    }

    /**
     * Each priority may appear at most once in the target list.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('targets')) {
            return;
        }

        $targets = (array) $this->input('targets', []);

        $priorities = array_values(array_map(
            static fn (array $target): string => strtoupper((string) ($target['priority'] ?? '')),
            $targets,
        ));

        $duplicates = array_diff_assoc($priorities, array_unique($priorities));

        if ($duplicates !== []) {
            $this->merge(['targets' => array_merge($targets, [['__duplicate_priority' => true]])]);
        }
    }
}
