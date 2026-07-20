<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\Search;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    private array $dummyQuestions = [['id' => 'q1', 'type' => 'text', 'label' => 'Pergunta', 'required' => true, 'order' => 1]];

    /**
     * REGRA 1: Diretor só pode convidar outro DIRETOR para gerenciar uma pesquisa (Sucesso).
     */
    public function test_director_can_invite_another_director_as_manager(): void
    {
        $directorSender = User::create(['type' => 'director', 'name' => 'Diretor A', 'email' => 'da@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        $directorRecipient = User::create(['type' => 'director', 'name' => 'Diretor B', 'email' => 'db@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        
        $search = Search::create(['title' => 'Pesquisa Global', 'status' => 'draft', 'questions' => $this->dummyQuestions, 'author_id' => $directorSender->id]);

        $response = $this->actingAs($directorSender, 'sanctum')->postJson('/api/invitations', [
            'search_id' => $search->id,
            'target_type' => 'manager',
            'strategy' => 'individual',
            'recipient_id' => $directorRecipient->id
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('invitations', ['search_id' => $search->id, 'user_id' => $directorRecipient->id, 'target_type' => 'manager']);
    }

    /**
     * REGRA 1 (Bloqueio): Diretor tenta chamar um usuário comum para gerenciar (Deve falhar).
     */
    public function test_director_cannot_invite_common_user_as_manager(): void
    {
        $director = User::create(['type' => 'director', 'name' => 'Diretor', 'email' => 'd@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        $commonUser = User::create(['type' => 'common', 'name' => 'Médico', 'email' => 'med@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        
        $search = Search::create(['title' => 'Pesquisa', 'status' => 'draft', 'questions' => $this->dummyQuestions, 'author_id' => $director->id]);

        $response = $this->actingAs($director, 'sanctum')->postJson('/api/invitations', [
            'search_id' => $search->id,
            'target_type' => 'manager',
            'strategy' => 'individual',
            'recipient_id' => $commonUser->id
        ]);

        $response->assertStatus(403);
    }

    /**
     * REGRA 2: Diretor só pode convidar POR ESPECIALIDADE usuários da mesma especialidade que a dele.
     */
    public function test_director_can_only_send_bulk_specialty_invites_for_their_own_specialties(): void
    {
        $director = User::create(['type' => 'director', 'name' => 'Diretor', 'email' => 'd@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        $specBelongs = Specialty::create(['name' => 'Pediatria']);
        $specOther = Specialty::create(['name' => 'Cardiologia']);
        
        $director->specialties()->sync([$specBelongs->id]);
        $search = Search::create(['title' => 'Pesquisa', 'status' => 'draft', 'questions' => $this->dummyQuestions, 'author_id' => $director->id]);

        // 1. Enviando para uma especialidade que ele NÃO gerencia (Deve retornar 403)
        $responseFail = $this->actingAs($director, 'sanctum')->postJson('/api/invitations', [
            'search_id' => $search->id,
            'target_type' => 'responder',
            'strategy' => 'specialty',
            'specialties' => [$specOther->id]
        ]);
        $responseFail->assertStatus(403);

        // 2. Enviando para a especialidade que ele gerencia (Deve retornar 200)
        $responseSuccess = $this->actingAs($director, 'sanctum')->postJson('/api/invitations', [
            'search_id' => $search->id,
            'target_type' => 'responder',
            'strategy' => 'specialty',
            'specialties' => [$specBelongs->id]
        ]);
        $responseSuccess->assertStatus(200);
    }

    /**
     * REGRA 3: Diretor pode chamar médicos de outras especialidades se a pesquisa for GLOBAL.
     */
    public function test_director_can_invite_outside_specialty_doctors_if_search_is_global(): void
    {
        $director = User::create(['type' => 'director', 'name' => 'Diretor', 'email' => 'd@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        $doctorFromOtherSpec = User::create(['type' => 'common', 'name' => 'Doutor Outro', 'email' => 'outro@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        
        $spec1 = Specialty::create(['name' => 'Pediatria']);
        $spec2 = Specialty::create(['name' => 'Cardiologia']);
        
        $director->specialties()->sync([$spec1->id]);
        $doctorFromOtherSpec->specialties()->sync([$spec2->id]); // Ele é da Cardio, Diretor é da Pediatria

        // Pesquisa Global (Sem chaves/vínculos de especialidade cadastrados)
        $globalSearch = Search::create(['title' => 'Pesquisa Institucional AMB', 'status' => 'draft', 'questions' => $this->dummyQuestions, 'author_id' => $director->id]);

        $response = $this->actingAs($director, 'sanctum')->postJson('/api/invitations', [
            'search_id' => $globalSearch->id,
            'target_type' => 'responder',
            'strategy' => 'individual',
            'recipient_id' => $doctorFromOtherSpec->id
        ]);

        $response->assertStatus(200); // Sucesso liberado por ser uma pesquisa global
    }

    /**
     * REGRA 3 (Bloqueio): Diretor tenta convidar médico de fora para uma pesquisa com especialidade segmentada (Deve falhar).
     */
    public function test_director_cannot_invite_outside_specialty_doctors_if_search_is_segmented(): void
    {
        $director = User::create(['type' => 'director', 'name' => 'Diretor', 'email' => 'd@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        $doctorFromOtherSpec = User::create(['type' => 'common', 'name' => 'Doutor Outro', 'email' => 'outro@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        
        $spec1 = Specialty::create(['name' => 'Pediatria']);
        $spec2 = Specialty::create(['name' => 'Cardiologia']);
        
        $director->specialties()->sync([$spec1->id]);
        $doctorFromOtherSpec->specialties()->sync([$spec2->id]);

        // Pesquisa Segmentada vinculada à Pediatria
        $segmentedSearch = Search::create(['title' => 'Pesquisa Segmentada', 'status' => 'draft', 'questions' => $this->dummyQuestions, 'author_id' => $director->id]);
        $segmentedSearch->specialties()->sync([$spec1->id]);

        $response = $this->actingAs($director, 'sanctum')->postJson('/api/invitations', [
            'search_id' => $segmentedSearch->id,
            'target_type' => 'responder',
            'strategy' => 'individual',
            'recipient_id' => $doctorFromOtherSpec->id
        ]);

        $response->assertStatus(403); // Bloqueado, pois a pesquisa é restrita por área
    }

    /**
     * Testar o ciclo completo do Aceite do convite.
     */
    public function test_user_can_accept_invitation_and_activate_pivots(): void
    {
        $director = User::create(['type' => 'director', 'name' => 'Diretor', 'email' => 'd@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        $doctor = User::create(['type' => 'common', 'name' => 'Médico', 'email' => 'm@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        $search = Search::create(['title' => 'Pesquisa', 'status' => 'published', 'questions' => $this->dummyQuestions, 'author_id' => $director->id]);

        // Cria convite pendente
        $invitation = Invitation::create([
            'search_id' => $search->id,
            'sender_id' => $director->id,
            'email' => $doctor->email,
            'user_id' => $doctor->id,
            'target_type' => 'responder',
            'status' => 'pending'
        ]);

        $response = $this->actingAs($doctor, 'sanctum')->postJson("/api/invitations/{$invitation->id}/accept");

        $response->assertStatus(200);
        $this->assertDatabaseHas('invitations', ['id' => $invitation->id, 'status' => 'accepted']);
        $this->assertDatabaseHas('search_targets', ['search_id' => $search->id, 'user_id' => $doctor->id]); // Vínculo ativo!
    }
}