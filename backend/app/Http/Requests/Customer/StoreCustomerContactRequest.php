<?php

namespace App\Http\Requests\Customer;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCustomerContactRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'is_primary' => ['nullable', 'boolean'],
            'user_id' => [
                'nullable',
                'uuid',
                Rule::exists('users', 'id')
                    ->where('organization_id', $this->user()->organization_id),
            ],
        ];
    }

    /**
     * Ensure the linked user (when given) belongs to the same organization as
     * the contact's customer, so cross-tenant user existence is never revealed.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $userId = $this->input('user_id');

            if ($userId === null) {
                return;
            }

            /** @var User|null $user */
            $user = User::query()
                ->whereKey($userId)
                ->first();

            if ($user === null || $user->organization_id !== $this->user()->organization_id) {
                $validator->errors()->add('user_id', 'The selected user is invalid.');
            }
        });
    }
}
