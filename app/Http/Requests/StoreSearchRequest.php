<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->type, ['master', 'director']);
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'objective' => 'nullable|string',
            'consent_term' => 'nullable|string',
            'instructions' => 'nullable|string',
            'status' => 'required|in:draft,published,closed,archived',
            'start_date' => [
                'nullable',
                'date',
                'after_or_equal:now',
            ],
            'end_date' => [
                'nullable',
                'date',
                'after:start_date',
            ],
            'specialties' => 'nullable|array', 
            'specialties.*' => 'exists:specialties,id',

            'questions' => 'nullable|array',
            'questions.*.id' => 'required_with:questions|string',
            'questions.*.type' => 'required_with:questions|string',
            'questions.*.label' => 'required_with:questions|string|max:255',
            'questions.*.required' => 'required_with:questions|boolean',
            'questions.*.order' => 'required_with:questions|integer',
            
            'questions.*.max_length' => 'nullable|integer',
            'questions.*.visible' => 'nullable|boolean',
            'questions.*.options' => 'nullable|array',
            'questions.*.options.*' => 'nullable|string|max:255',
        ];
    }
}