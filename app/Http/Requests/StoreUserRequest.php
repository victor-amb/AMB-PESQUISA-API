<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Tanto Master quanto Diretor podem criar usuários agora
        return in_array($this->user()?->type, ['master', 'director']);
    }

    public function rules(): array
    {
        $isDirector = $this->user()?->type === 'director';

        return [
            // Se for diretor, ele não pode criar um usuário "master"
            'type' => $isDirector ? 'required|in:director,common' : 'required|in:master,director,common',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'crm' => 'nullable|integer',
            'crm_state' => 'nullable|string|size:2',
            'specialties' => 'nullable|array',
            'specialties.*' => 'exists:specialties,id',
            
            // OBRIGATÓRIO para Diretores informarem qual pesquisa o novo usuário fará parte
            'search_id' => $isDirector ? 'required|exists:searches,id' : 'nullable|exists:searches,id',
        ];
    }
}