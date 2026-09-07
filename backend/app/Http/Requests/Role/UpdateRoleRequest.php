<?php

namespace App\Http\Requests\Role;

use App\Models\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::exists(Permission::class, 'name')],
        ];
    }

    /**
     * Prepare the data for validation: normalise the permissions list to a
     * flat array of names so nested/object payloads do not slip through.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('permissions')) {
            $this->merge([
                'permissions' => array_values(array_unique(array_filter(
                    $this->input('permissions', []),
                    'is_string',
                ))),
            ]);
        }
    }
}
