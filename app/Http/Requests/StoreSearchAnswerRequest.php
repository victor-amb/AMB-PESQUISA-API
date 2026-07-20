<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSearchAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search_id' => 'required|integer|exists:searches,id',
            'answers' => 'present|array',
            'progress_status' => 'sometimes|string|in:in_progress,completed'
        ];
    }
}