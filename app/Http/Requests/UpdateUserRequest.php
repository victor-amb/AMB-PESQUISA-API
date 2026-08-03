<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // O middleware Sanctum garante a autenticação. O escopo fino já está no Controller.
    }

    public function rules(): array
    {
        $user = $this->user();

        // CONTEXTO 1: O usuário está editando o PRÓPRIO perfil (/api/profile)
        if ($this->is('api/profile')) {
            $tableName = $user->getTable();
            
            $rules = [
                'name' => 'required|string|max:255',
                'email' => "required|email|max:255|unique:{$tableName},email,{$user->id}",
                'current_password' => 'required_with:password|string',
                'password' => 'nullable|string|min:8',
            ];

            // Médicos (Responders) precisam enviar CRM na edição do próprio perfil
            if ($user instanceof \App\Models\Responder) {
                $rules['crm'] = 'required|integer';
                $rules['crm_state'] = 'required|string|size:2';
            }

            return $rules;
        }

        // CONTEXTO 2: Um Administrador editando outro usuário no Painel (/api/users/{id})
        $targetUserId = $this->route('user');
        $isDirector = $user->type === 'director';

        return [
            'type' => $isDirector ? 'sometimes|in:director' : 'sometimes|in:master,director',
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|max:255|unique:users,email,' . $targetUserId,
            'password' => 'nullable|string|min:8',
            'active' => 'sometimes|boolean',
            'specialties' => 'nullable|array',
            'specialties.*' => 'exists:specialties,id',
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required_with' => 'Para alterar a senha, é necessário informar a senha atual.',
        ];
    }
}