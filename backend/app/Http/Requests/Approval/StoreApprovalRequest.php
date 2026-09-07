<?php

namespace App\Http\Requests\Approval;

use App\Enums\ApprovalType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApprovalRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'assigned_to' => [
                'required',
                'uuid',
                Rule::exists('users', 'id')
                    ->where('organization_id', $this->user()->organization_id),
            ],
            'type' => ['required', Rule::enum(ApprovalType::class)],
            'title' => ['required', 'string', 'min:1', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'due_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'assigned_to.exists' => 'The selected assignee is invalid.',
        ];
    }
}
