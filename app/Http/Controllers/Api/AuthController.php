<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Models\Responder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Realiza a autenticação e gera o Token da API direcionando para a tabela correta
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        
        // DIRECIONAMENTO DE PORTAL: admin (tabela users) ou app (tabela responders)
        $portal = $request->input('portal', 'app'); 

        if ($portal === 'admin') {
            // Busca operadores administrativos (Master / Director)
            $account = User::where('email', $data['email'])->first();
        } else {
            // Busca médicos comuns e respondentes externos gerais
            $account = Responder::where('email', $data['email'])->first();
        }

        // Verifica se o registro existe e se a senha está correta
        if (!$account || !Hash::check($data['password'], $account->password)) {
            return response()->json([
                'message' => 'As credenciais fornecidas estão incorretas.'
            ], 421);
        }

        if (!$account->active) {
            return response()->json([
                'message' => 'Este usuário está desativado. Entre em contato com a administração central.'
            ], 403);
        }

        // Gera o token do Sanctum mapeando de forma polimórfica a origem (User ou Responder)
        $token = $account->createToken('amb_pesquisas_token')->plainTextToken;

        // Injeta a flag ou o tipo correto na resposta para o Frontend se orientar na rota
        $accountType = $account instanceof User ? $account->type : 'responder';

        return response()->json([
            'message' => 'Login realizado com sucesso!',
            'token' => $token,
            'user' => [
                'id' => $account->id,
                'name' => $account->name,
                'email' => $account->email,
                'type' => $accountType,
            ]
        ]);
    }

    /**
     * Retorna os dados da conta autenticada (Sabe tratar se é User corporativo ou Responder)
     */
    public function me(Request $request): JsonResponse
    {
        $account = $request->user();

        // Evita quebra de código carregando a relação em ambos os cenários de model
        if ($account instanceof User || $account instanceof Responder) {
            $account->load('specialties');
        }
        
        return response()->json($account);
    }

    /**
     * Revoga o token de acesso atual (Logout seguro)
     */
    public function logout(Request $request): JsonResponse
    {
        // Uso preventivo do operador null-safe para testes automatizados
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logout realizado com sucesso e token revogado.']);
    }
}