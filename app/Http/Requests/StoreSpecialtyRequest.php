<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSpecialtyRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Apenas Master gerencia especialidades
        return $this->user()?->type === 'master';
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'image_path' => 'nullable|string|max:255',
            'active' => 'boolean'
        ];
    }
}