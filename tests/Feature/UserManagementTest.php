<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Testar Regra: Usuário atualiza seus próprios dados na rota /api/profile,
     * mas campos administrativos (type, active) são ignorados.
     */
    public function test_user_can_update_own_profile_but_admin_fields_are_ignored(): void
    {
        $user = User::create([
            'type' => 'director', 
            'name' => 'Diretor Original',
            'email' => 'diretor@amb.com.br',
            'password' => bcrypt('senha123'),
            'active' => true
        ]);

        $response = $this->actingAs($user, 'sanctum')->putJson('/api/profile', [
            'name' => 'Diretor Modificado',
            'email' => 'novo.email@amb.com.br',
            'type' => 'master', // Ignorado pelo ProfileController
            'active' => false   // Ignorado pelo ProfileController
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Diretor Modificado',
            'type' => 'director', 
            'active' => true      
        ]);
    }

    /**
     * Testar Regra: O Master pode editar a si mesmo no CRUD, MAS seu próprio type e active não mudam.
     */
    public function test_master_cannot_update_own_type_and_active(): void
    {
        $master = User::create([
            'type' => 'master', 'name' => 'Admin', 'email' => 'master@amb.com.br', 'password' => bcrypt('123'), 'active' => true
        ]);

        $response = $this->actingAs($master, 'sanctum')->putJson("/api/users/{$master->id}", [
            'name' => 'Admin Editado',
            'type' => 'director', // Tentativa ignorada
            'active' => false     // Tentativa ignorada
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $master->id,
            'name' => 'Admin Editado',
            'type' => 'master', // Mantém intacto
            'active' => true    // Mantém intacto
        ]);
    }

    /**
     * Testar Regra: Master pode alterar o type e active de outros usuários (ex: Diretores).
     */
    public function test_master_can_update_type_and_active_of_other_non_master_users(): void
    {
        $master = User::create([
            'type' => 'master', 'name' => 'Admin', 'email' => 'master@amb.com.br', 'password' => bcrypt('123'), 'active' => true
        ]);

        $directorUser = User::create([
            'type' => 'director', 'name' => 'João', 'email' => 'joao@amb.com.br', 'password' => bcrypt('123'), 'active' => true
        ]);

        $response = $this->actingAs($master, 'sanctum')->putJson("/api/users/{$directorUser->id}", [
            'name' => 'João Atualizado',
            'email' => 'joao.novo@amb.com.br',
            'active' => false // Desativando o diretor
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'id' => $directorUser->id,
            'name' => 'João Atualizado',
            'active' => false
        ]);
    }

    /**
     * Testar Regra: Master NÃO pode alterar dados de outros usuários do tipo Master.
     */
    public function test_master_cannot_update_another_master_user(): void
    {
        $master1 = User::create([
            'type' => 'master', 'name' => 'Master Um', 'email' => 'm1@amb.com.br', 'password' => bcrypt('123'), 'active' => true
        ]);

        $master2 = User::create([
            'type' => 'master', 'name' => 'Master Dois', 'email' => 'm2@amb.com.br', 'password' => bcrypt('123'), 'active' => true
        ]);

        $response = $this->actingAs($master1, 'sanctum')->putJson("/api/users/{$master2->id}", [
            'name' => 'Nome Invasor',
            'email' => 'm2.invasao@amb.com.br'
        ]);

        // Barrado pelo Controller!
        $response->assertStatus(403);
    }
}