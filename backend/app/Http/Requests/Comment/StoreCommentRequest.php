<?php

namespace App\Http\Requests\Comment;

use App\Enums\CommentVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommentRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:1', 'max:10000'],
            'visibility' => ['nullable', Rule::enum(CommentVisibility::class)],
        ];
    }
}
