<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Autenticação já validada pelo Sanctum na rota
    }

    public function rules(): array
    {
        $user = $this->user();

        $rules = [
            'name'  => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            
            // Regra para alteração de senha logado
            'current_password' => 'nullable|string|required_with:password',
            'password'         => 'nullable|string|min:8|confirmed', // 'confirmed' exige o campo password_confirmation
        ];

        // Regra específica para o ecossistema do Diretor
        if ($user->type === 'director') {
            $rules['crm']       = 'required|string|max:20';
            $rules['crm_state'] = 'required|string|size:2';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Este endereço de e-mail já está sendo utilizado por outro usuário do sistema.',
            'current_password.required_with' => 'Você precisa informar sua senha atual para definir uma nova senha.',
            'password.confirmed' => 'A confirmação da nova senha não confere.',
            'password.min' => 'A nova senha deve conter no mínimo 8 caracteres.',
        ];
    }
}