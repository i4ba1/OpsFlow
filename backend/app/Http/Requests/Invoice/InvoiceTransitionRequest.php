<?php

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Empty form request for the invoice lifecycle transitions (issue, mark paid,
 * void). The required capability is enforced by the permission middleware on
 * each controller route; there is no payload to validate.
 */
class InvoiceTransitionRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
