<?php

namespace App\Http\Requests\Project;

use App\Enums\ProjectStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
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
            'customer_id' => [
                'required',
                'uuid',
                Rule::exists('customers', 'id')
                    ->where('organization_id', $organizationId),
            ],
            'status' => ['nullable', Rule::enum(ProjectStatus::class)],
            'starts_on' => ['nullable', 'date', 'before_or_equal:ends_on'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'manager_id' => [
                'nullable',
                'uuid',
                Rule::exists('users', 'id')
                    ->where('organization_id', $organizationId),
            ],
        ];
    }
}
