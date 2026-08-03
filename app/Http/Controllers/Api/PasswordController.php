<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetMail;
use App\Models\Responder;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PasswordController extends Controller
{
    /**
     * @tags Credenciais
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $email = $request->email;
        
        // Mensagem genérica para prevenir enumeração de e-mails
        $successResponse = response()->json([
            'message' => 'Se o e-mail estiver cadastrado, você receberá as instruções em instantes.'
        ], 200);

        // 1. Tentar achar na tabela Users (Painel Administrativo)
        $user = User::where('email', $email)->first();
        $portal = 'admin';

        if (!$user) {
            // 2. Tentar achar na tabela Responders (App de Resposta)
            $user = Responder::where('email', $email)->first();
            $portal = 'app';
        }

        // Se não existir em nenhum, retorna a mesma resposta sem fazer nada
        if (!$user) {
            return $successResponse;
        }

        // 3. Criar ou atualizar token
        $token = Str::random(64);
        
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token' => $token,
                'created_at' => now()
            ]
        );

        // 4. Montar a URL apropriada baseado no portal e enviar o email
        $baseUrl = $portal === 'admin' 
            ? env('ADMIN_URL', 'http://localhost:8011/')
            : env('RESPONDER_URL', 'http://localhost:8012/');
            
        // Garante que o baseUrl termina com / se não tiver
        $baseUrl = rtrim($baseUrl, '/') . '/';
        $resetUrl = $baseUrl . 'redefinir-senha?token=' . $token . '&email=' . urlencode($email);

        try {
            Mail::to($email)->send(new PasswordResetMail($resetUrl, $user->name));
        } catch (\Exception $e) {
            // Log do erro silenciosamente
            \Log::error('Erro ao enviar email de reset: ' . $e->getMessage());
        }

        return $successResponse;
    }

    /**
     * @tags Credenciais
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed'
        ]);

        $reset = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where('token', $request->token)
            ->first();

        // Verifica se o token existe e se não é mais antigo que 60 minutos
        if (!$reset || \Carbon\Carbon::parse($reset->created_at)->addMinutes(60)->isPast()) {
            return response()->json([
                'message' => 'O link de recuperação é inválido ou já expirou. Solicite um novo.'
            ], 422);
        }

        // Busca o usuário na tabela Users ou Responders
        $user = User::where('email', $request->email)->first() 
             ?? Responder::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'Usuário não encontrado.'
            ], 422);
        }

        // Atualiza a senha
        $user->update([
            'password' => Hash::make($request->password)
        ]);

        // Remove o token utilizado
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json([
            'message' => 'Senha alterada com sucesso! Faça seu login.'
        ]);
    }

    /**
     * @tags Credenciais
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed|different:current_password'
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'errors' => [
                    'current_password' => ['A senha atual informada está incorreta.']
                ]
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->password)
        ]);

        // Opcional de segurança: se necessário, poderia deletar outros tokens Sanctum
        // $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        return response()->json([
            'message' => 'Senha alterada com sucesso!'
        ]);
    }
}
