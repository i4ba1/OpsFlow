<?php

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $organizationId = $this->user()->organization_id;

        return [
            'customer_id' => [
                'sometimes',
                'required',
                'uuid',
                Rule::exists('customers', 'id')
                    ->where('organization_id', $organizationId),
            ],
            'project_id' => [
                'nullable',
                'uuid',
                Rule::exists('projects', 'id')
                    ->where('organization_id', $organizationId)
                    ->where('customer_id', $this->input('customer_id', $this->route('invoice')?->customer_id)),
            ],
            'currency' => ['sometimes', 'string', 'size:3'],
            'due_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['sometimes', 'array', 'min:1', 'max:100'],
            'items.*.description' => ['required', 'string', 'min:1', 'max:500'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:9999999999999999.99'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.exists' => 'The selected customer is invalid.',
            'project_id.exists' => 'The selected project is invalid.',
            'items.min' => 'An invoice must have at least one line item.',
        ];
    }
}
