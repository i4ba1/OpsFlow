<?php

namespace App\Http\Requests\ServiceRequest;

use App\Enums\ServiceRequestPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeServiceRequestPriorityRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'priority' => ['required', Rule::enum(ServiceRequestPriority::class)],
        ];
    }
}
