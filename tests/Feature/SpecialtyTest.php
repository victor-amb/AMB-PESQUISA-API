<?php

namespace Tests\Feature;

use App\Models\Specialty;
use App\Models\User;
use App\Models\Responder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpecialtyTest extends TestCase
{
    use RefreshDatabase;

    public function test_specialty_routes_require_authentication(): void
    {
        $this->getJson('/api/specialties')->assertStatus(401);
        $this->postJson('/api/specialties', [])->assertStatus(401);
        $this->putJson('/api/specialties/1', [])->assertStatus(401);
        $this->deleteJson('/api/specialties/1')->assertStatus(401);
    }

    public function test_master_user_can_list_all_specialties(): void
    {
        $master = User::create(['type' => 'master', 'name' => 'Admin', 'email' => 'm@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        
        Specialty::create(['name' => 'Pediatria', 'active' => true]);
        Specialty::create(['name' => 'Cardiologia', 'active' => true]);
        Specialty::create(['name' => 'Neurologia', 'active' => true]);

        $response = $this->actingAs($master, 'sanctum')->getJson('/api/specialties');

        $response->assertStatus(200)->assertJsonCount(3, 'data');
    }

    public function test_non_master_users_cannot_list_specialties(): void
    {
        $director = User::create(['type' => 'director', 'name' => 'Diretor', 'email' => 'd@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        $doctor = Responder::create(['name' => 'Médico', 'email' => 'med@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);

        // Ambos devem ser bloqueados ao tentar acessar a listagem
        $this->actingAs($director, 'sanctum')->getJson('/api/specialties')->assertStatus(403);
        $this->actingAs($doctor, 'sanctum')->getJson('/api/specialties')->assertStatus(403);
    }

    public function test_only_master_user_can_create_specialties(): void
    {
        $master = User::create(['type' => 'master', 'name' => 'Admin', 'email' => 'm@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        $director = User::create(['type' => 'director', 'name' => 'Diretor', 'email' => 'd@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);

        $payload = ['name' => 'Dermatologia', 'image_path' => '/assets/img.jpg', 'active' => true];

        $this->actingAs($director, 'sanctum')
             ->postJson('/api/specialties', $payload)
             ->assertStatus(403);

        $this->actingAs($master, 'sanctum')
             ->postJson('/api/specialties', $payload)
             ->assertStatus(201);

        $this->assertDatabaseHas('specialties', ['name' => 'Dermatologia']);
    }

    public function test_only_master_user_can_update_specialties(): void
    {
        $master = User::create(['type' => 'master', 'name' => 'Admin', 'email' => 'm@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        $doctor = Responder::create(['name' => 'Médico', 'email' => 'med@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        
        $spec = Specialty::create(['name' => 'Urologia', 'active' => true]);

        $this->actingAs($doctor, 'sanctum')
             ->putJson("/api/specialties/{$spec->id}", ['name' => 'Urologia Alterada'])
             ->assertStatus(403);

        $this->actingAs($master, 'sanctum')
             ->putJson("/api/specialties/{$spec->id}", ['name' => 'Urologia Modificada'])
             ->assertStatus(200);

        $this->assertDatabaseHas('specialties', ['id' => $spec->id, 'name' => 'Urologia Modificada']);
    }

    public function test_only_master_user_can_deactivate_specialties(): void
    {
        $master = User::create(['type' => 'master', 'name' => 'Admin', 'email' => 'm@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        $director = User::create(['type' => 'director', 'name' => 'Diretor', 'email' => 'd@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);

        $spec = Specialty::create(['name' => 'Gastroenterologia', 'active' => true]);

        $this->actingAs($director, 'sanctum')
             ->deleteJson("/api/specialties/{$spec->id}")
             ->assertStatus(403);

        $this->actingAs($master, 'sanctum')
             ->deleteJson("/api/specialties/{$spec->id}")
             ->assertStatus(200);

        $this->assertDatabaseHas('specialties', ['id' => $spec->id, 'active' => false]);
    }
}