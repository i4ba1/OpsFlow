<?php

namespace App\Http\Requests\SLA;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBusinessCalendarRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'timezone' => ['sometimes', 'string', 'timezone'],
            'description' => ['nullable', 'string', 'max:5000'],
            'hours' => ['sometimes', 'array'],
            'hours.*.day_of_week' => ['required_with:hours.*.opens_at', 'integer', 'between:0,6'],
            'hours.*.opens_at' => ['required_with:hours.*.day_of_week', 'date_format:H:i'],
            'hours.*.closes_at' => ['required_with:hours.*.day_of_week', 'date_format:H:i', 'after:hours.*.opens_at'],
            'holidays' => ['sometimes', 'array'],
            'holidays.*.name' => ['required_with:holidays.*.date', 'string', 'max:255'],
            'holidays.*.date' => ['required_with:holidays.*.name', 'date', 'date_format:Y-m-d'],
        ];
    }
}
