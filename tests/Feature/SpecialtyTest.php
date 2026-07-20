<?php

namespace Tests\Feature;

use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpecialtyTest extends TestCase
{
    use RefreshDatabase; // Garante banco limpo e isolado para cada teste

    /**
     * Testar se as rotas de Especialidade exigem autenticação prévia.
     */
    public function test_specialty_routes_require_authentication(): void
    {
        $this->getJson('/api/specialties')->assertStatus(401);
        $this->postJson('/api/specialties', [])->assertStatus(401);
        $this->putJson('/api/specialties/1', [])->assertStatus(401);
        $this->deleteJson('/api/specialties/1')->assertStatus(401);
    }

    /**
     * Testar Regra: Usuário MASTER pode listar TODAS as especialidades.
     */
    public function test_master_user_can_list_all_specialties(): void
    {
        $master = User::create(['type' => 'master', 'name' => 'Admin', 'email' => 'm@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        
        // Cria 3 especialidades no banco de testes
        Specialty::create(['name' => 'Pediatria', 'active' => true]);
        Specialty::create(['name' => 'Cardiologia', 'active' => true]);
        Specialty::create(['name' => 'Neurologia', 'active' => true]);

        $response = $this->actingAs($master, 'sanctum')->getJson('/api/specialties');

        $response->assertStatus(200)->assertJsonCount(3); // Deve ver as 3
    }

    /**
     * Testar Regra: Usuário DIRETOR só pode listar as especialidades onde está cadastrado.
     */
    public function test_director_user_can_only_list_their_linked_specialties(): void
    {
        $director = User::create(['type' => 'director', 'name' => 'Diretor', 'email' => 'd@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        
        $spec1 = Specialty::create(['name' => 'Pediatria', 'active' => true]);
        $spec2 = Specialty::create(['name' => 'Cardiologia', 'active' => true]);
        Specialty::create(['name' => 'Neurologia', 'active' => true]); // Especialidade "solta"

        // Vincula o diretor apenas à Pediatria e Cardiologia
        $director->specialties()->sync([$spec1->id, $spec2->id]);

        $response = $this->actingAs($director, 'sanctum')->getJson('/api/specialties');

        $response->assertStatus(200)
                 ->assertJsonCount(2) // Só deve ver 2 especialidades
                 ->assertJsonPath('0.name', 'Cardiologia')
                 ->assertJsonPath('1.name', 'Pediatria'); // Retorna em ordem alfabética
    }

    /**
     * Testar Regra: Usuário COMUM (Médico) só pode listar as especialidades onde está cadastrado.
     */
    public function test_common_user_can_only_list_their_linked_specialties(): void
    {
        $doctor = User::create(['type' => 'common', 'name' => 'Médico', 'email' => 'med@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        
        $spec1 = Specialty::create(['name' => 'Pediatria', 'active' => true]);
        Specialty::create(['name' => 'Cardiologia', 'active' => true]);

        // Vincula o médico apenas à Pediatria
        $doctor->specialties()->sync([$spec1->id]);

        $response = $this->actingAs($doctor, 'sanctum')->getJson('/api/specialties');

        $response->assertStatus(200)
                 ->assertJsonCount(1) // Só deve ver 1
                 ->assertJsonPath('0.name', 'Pediatria');
    }

    /**
     * Testar Regra: Somente Usuário MASTER pode CRIAR especialidades.
     */
    public function test_only_master_user_can_create_specialties(): void
    {
        $master = User::create(['type' => 'master', 'name' => 'Admin', 'email' => 'm@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        $director = User::create(['type' => 'director', 'name' => 'Diretor', 'email' => 'd@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);

        $payload = ['name' => 'Dermatologia', 'image_path' => '/assets/img.jpg', 'active' => true];

        // 1. Tentativa com Diretor (Deve dar erro 403)
        $this->actingAs($director, 'sanctum')
             ->postJson('/api/specialties', $payload)
             ->assertStatus(403);

        // 2. Tentativa com Master (Deve criar com sucesso 201)
        $this->actingAs($master, 'sanctum')
             ->postJson('/api/specialties', $payload)
             ->assertStatus(201);

        $this->assertDatabaseHas('specialties', ['name' => 'Dermatologia']);
    }

    /**
     * Testar Regra: Somente Usuário MASTER pode EDITAR especialidades.
     */
    public function test_only_master_user_can_update_specialties(): void
    {
        $master = User::create(['type' => 'master', 'name' => 'Admin', 'email' => 'm@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        $doctor = User::create(['type' => 'common', 'name' => 'Médico', 'email' => 'med@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        
        $spec = Specialty::create(['name' => 'Urologia', 'active' => true]);
        $doctor->specialties()->sync([$spec->id]); // Dá a ele acesso visual, mas não de edição

        // 1. Tentativa com Médico Comum (Deve falhar)
        $this->actingAs($doctor, 'sanctum')
             ->putJson("/api/specialties/{$spec->id}", ['name' => 'Urologia Alterada'])
             ->assertStatus(403);

        // 2. Tentativa com Master (Deve funcionar)
        $this->actingAs($master, 'sanctum')
             ->putJson("/api/specialties/{$spec->id}", ['name' => 'Urologia Modificada'])
             ->assertStatus(200);

        $this->assertDatabaseHas('specialties', ['id' => $spec->id, 'name' => 'Urologia Modificada']);
    }

    /**
     * Testar Regra: Somente Usuário MASTER pode DESATIVAR (excluir logicamente) especialidades.
     */
    public function test_only_master_user_can_deactivate_specialties(): void
    {
        $master = User::create(['type' => 'master', 'name' => 'Admin', 'email' => 'm@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        $director = User::create(['type' => 'director', 'name' => 'Diretor', 'email' => 'd@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);

        $spec = Specialty::create(['name' => 'Gastroenterologia', 'active' => true]);

        // 1. Tentativa com Diretor (Deve falhar)
        $this->actingAs($director, 'sanctum')
             ->deleteJson("/api/specialties/{$spec->id}")
             ->assertStatus(403);

        // 2. Tentativa com Master (Deve mudar active para false)
        $this->actingAs($master, 'sanctum')
             ->deleteJson("/api/specialties/{$spec->id}")
             ->assertStatus(200);

        $this->assertDatabaseHas('specialties', ['id' => $spec->id, 'active' => false]);
    }
}