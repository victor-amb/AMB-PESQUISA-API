<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->type, ['master', 'director']);
    }

    public function rules(): array
    {
        $userId = $this->route('user');
        $isDirector = $this->user()?->type === 'director';

        return [
            'type' => $isDirector ? 'required|in:director,common' : 'required|in:master,director,common',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $userId,
            'password' => 'nullable|string|min:8',
            'crm' => 'nullable|integer',
            'crm_state' => 'nullable|string|size:2',
            'specialties' => 'nullable|array',
            'specialties.*' => 'exists:specialties,id',
        ];
    }
}