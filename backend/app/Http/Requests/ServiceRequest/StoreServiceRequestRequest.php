<?php

namespace App\Http\Requests\ServiceRequest;

use App\Enums\ServiceRequestPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreServiceRequestRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $organizationId = $this->user()->organization_id;

        return [
            // A customer user (linked to a CustomerContact) may omit the
            // customer and have it inferred from their contact; everyone else
            // must choose one. Enforced in withValidator().
            'customer_id' => [
                'nullable',
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
            // Phase 5: an SLA policy for the request. Org-scoped; when the
            // policy (or its priority target) is inactive the request simply
            // ends up without SLA due dates.
            'sla_policy_id' => [
                'nullable',
                'uuid',
                Rule::exists('sla_policies', 'id')
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
            'sla_policy_id.exists' => 'The selected SLA policy is invalid.',
        ];
    }

    /**
     * Require an explicit customer unless the authenticated user is a customer
     * contact (a customer-portal user), in which case the request is tied to
     * the customer they belong to.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || $this->filled('customer_id')) {
                return;
            }

            if (! $this->user()->customerContacts()->exists()) {
                $validator->errors()->add('customer_id', 'The customer field is required.');
            }
        });
    }
}
