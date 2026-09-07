<?php

namespace App\Http\Requests\Attachment;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttachmentRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $maxKb = (int) config('attachments.max_size', 25 * 1024 * 1024) / 1024;

        return [
            'file' => [
                'required',
                'file',
                'max:'.$maxKb,
                'mimes:'.implode(',', config('attachments.extensions', [])),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'A file is required.',
            'file.max' => 'The file exceeds the maximum allowed size.',
            'file.mimes' => 'The file type is not allowed.',
        ];
    }
}
