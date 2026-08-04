<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Responder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase; // Limpa o banco de dados temporário a cada teste

    /**
     * Testar se rotas protegidas barram usuários não autenticados.
     */
    public function test_protected_routes_require_authentication(): void
    {
        $response = $this->getJson('/api/me');
        $response->assertStatus(401); // Exige token

        $responseLogout = $this->postJson('/api/logout');
        $responseLogout->assertStatus(401);
    }

    /**
     * Testar login bem sucedido para usuário Administrador (Master/Director) no portal admin.
     */
    public function test_admin_user_can_login_with_correct_credentials(): void
    {
        $user = User::create([
            'type' => 'master',
            'name' => 'Dr. Master',
            'email' => 'master@amb.com.br',
            'password' => bcrypt('senha123'),
            'active' => true
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'master@amb.com.br',
            'password' => 'senha123',
            'portal' => 'admin' // <--- Parâmetro essencial para buscar na tabela users
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['message', 'token', 'user'])
                 ->assertJsonPath('user.type', 'master');
    }

    /**
     * Testar login bem sucedido para usuário Comum (Responder) no portal app.
     */
    public function test_responder_user_can_login_with_correct_credentials(): void
    {
        $responder = Responder::create([
            'name' => 'Dr. Comum',
            'email' => 'comum@amb.com.br',
            'password' => bcrypt('senha123'),
            'active' => true
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'comum@amb.com.br',
            'password' => 'senha123',
            'portal' => 'app' // <--- Parâmetro para buscar na tabela responders
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['message', 'token', 'user'])
                 ->assertJsonPath('user.type', 'responder');
    }

    /**
     * Testar se o sistema barra credenciais inválidas.
     */
    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        User::create([
            'type' => 'director',
            'name' => 'Dr. Teste',
            'email' => 'teste@amb.com.br',
            'password' => bcrypt('senha123'),
            'active' => true
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'teste@amb.com.br',
            'password' => 'senhaErrada',
            'portal' => 'admin'
        ]);

        $response->assertStatus(401); // Status retornado pelo AuthController
    }

    /**
     * Testar se usuários inativos são impedidos de logar.
     */
    public function test_inactive_user_cannot_login(): void
    {
        User::create([
            'type' => 'director',
            'name' => 'Dr. Inativo',
            'email' => 'inativo@amb.com.br',
            'password' => bcrypt('senha123'),
            'active' => false // Usuário desativado
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'inativo@amb.com.br',
            'password' => 'senha123',
            'portal' => 'admin'
        ]);

        $response->assertStatus(403); // Acesso proibido
    }

    /**
     * Testar se o endpoint /api/me retorna os dados corretos para um Admin logado.
     */
    public function test_me_endpoint_returns_authenticated_admin_data(): void
    {
        $user = User::create([
            'type' => 'director',
            'name' => 'Diretor Teste',
            'email' => 'diretor@amb.com.br',
            'password' => bcrypt('senha123'),
            'active' => true
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/me');

        $response->assertStatus(200)
                 ->assertJsonPath('email', 'diretor@amb.com.br')
                 ->assertJsonPath('type', 'director');
    }

    /**
     * Testar se o endpoint /api/me retorna os dados corretos para um Respondente logado.
     */
    public function test_me_endpoint_returns_authenticated_responder_data(): void
    {
        $responder = Responder::create([
            'name' => 'Responder Teste',
            'email' => 'responder@amb.com.br',
            'password' => bcrypt('senha123'),
            'active' => true
        ]);

        // Atua como o respondente logado via Sanctum
        $response = $this->actingAs($responder, 'sanctum')->getJson('/api/me');

        $response->assertStatus(200)
                 ->assertJsonPath('email', 'responder@amb.com.br')
                 ->assertJsonPath('name', 'Responder Teste');
    }

    /**
     * Testar se o logout destrói e revoga o token de acesso.
     */
    public function test_user_can_logout_and_revoke_token(): void
    {
        $user = User::create([
            'type' => 'director',
            'name' => 'Dr. Teste',
            'email' => 'teste@amb.com.br',
            'password' => bcrypt('senha123'),
            'active' => true
        ]);

        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/logout');
        $response->assertStatus(200);

        // Verifica se o token foi excluído da tabela personal_access_tokens
        $this->assertCount(0, $user->tokens);
    }
}