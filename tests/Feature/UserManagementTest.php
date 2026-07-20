<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Testar Regra: Usuário comum atualiza seus próprios dados (Profile),
     * mas campos administrativos (type, active) são sumariamente ignorados.
     */
    public function test_user_can_update_own_profile_but_admin_fields_are_ignored(): void
    {
        $user = User::create([
            'type' => 'common',
            'name' => 'Médico Original',
            'email' => 'medico@amb.com.br',
            'crm' => 1234,
            'crm_state' => 'SP',
            'password' => bcrypt('senha123'),
            'active' => true
        ]);

        $response = $this->actingAs($user, 'sanctum')->putJson('/api/profile', [
            'name' => 'Médico Modificado',
            'email' => 'novo.email@amb.com.br',
            'crm' => 5555,
            'crm_state' => 'RJ',
            // Tentativa maliciosa de se promover a master e se desativar:
            'type' => 'master',
            'active' => false
        ]);

        $response->assertStatus(200);

        // Verifica se os dados permitidos mudaram no banco
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Médico Modificado',
            'email' => 'novo.email@amb.com.br',
            'crm' => 5555,
            'crm_state' => 'RJ',
            // Verifica se a segurança funcionou e os campos continuam iguais aos originais
            'type' => 'common',
            'active' => true
        ]);
    }

    /**
     * Testar Regra: Master pode alterar dados de diretores ou comuns.
     */
    public function test_master_can_update_data_of_other_non_master_users(): void
    {
        $master = User::create([
            'type' => 'master', 'name' => 'Admin', 'email' => 'master@amb.com.br', 'password' => bcrypt('123'), 'active' => true
        ]);

        $commonUser = User::create([
            'type' => 'common', 'name' => 'João', 'email' => 'joao@amb.com.br', 'password' => bcrypt('123'), 'active' => true
        ]);

        $response = $this->actingAs($master, 'sanctum')->putJson("/api/users/{$commonUser->id}", [
            'type' => 'director', // Master promovendo o usuário a diretor
            'name' => 'João Diretor',
            'email' => 'joao.diretor@amb.com.br',
            'active' => true
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'id' => $commonUser->id,
            'type' => 'director',
            'name' => 'João Diretor'
        ]);
    }

    /**
     * Testar Regra: Master NÃO pode alterar dados de outros usuários do tipo Master.
     */
    public function test_master_cannot_update_another_master_user(): void
    {
        // Adicione esta trava no topo do método update() do seu UserController se quiser que este teste passe:
        // if ($user->type === 'master' && $currentUser->id !== $user->id) { return response()->json(['message' => 'Um Master não pode alterar outro Master.'], 403); }

        $master1 = User::create([
            'type' => 'master', 'name' => 'Master Um', 'email' => 'm1@amb.com.br', 'password' => bcrypt('123'), 'active' => true
        ]);

        $master2 = User::create([
            'type' => 'master', 'name' => 'Master Dois', 'email' => 'm2@amb.com.br', 'password' => bcrypt('123'), 'active' => true
        ]);

        $response = $this->actingAs($master1, 'sanctum')->putJson("/api/users/{$master2->id}", [
            'type' => 'master',
            'name' => 'Nome Invasor',
            'email' => 'm2@amb.com.br'
        ]);

        // Se você implementou a trava acima, o status esperado será 403
        $response->assertStatus(403);
    }
}