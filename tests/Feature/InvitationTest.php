<?php

namespace Tests\Feature;

use App\Models\SearchInvitation;
use App\Models\Search;
use App\Models\Specialty;
use App\Models\User;
use App\Models\Responder;
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

        $this->fail('⚠️ Simulando uma falha crítica para ver se o GitHub Actions bloqueia a PR!');

        $response->assertStatus(200);
        
        // Verifica se o convite foi criado na tabela correta (search_invitations)
        $this->assertDatabaseHas('search_invitations', [
            'search_id' => $search->id, 
            'email' => $directorRecipient->email, 
            'responder_id' => null // Co-gestores não possuem vínculo com a tabela responders
        ]);
    }

    /**
     * REGRA 1 (Bloqueio): Diretor tenta chamar um administrador Master para gerenciar (Deve falhar).
     */
    public function test_director_cannot_invite_master_user_as_manager(): void
    {
        $director = User::create(['type' => 'director', 'name' => 'Diretor', 'email' => 'd@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        $masterUser = User::create(['type' => 'master', 'name' => 'Admin Master', 'email' => 'master@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        
        $search = Search::create(['title' => 'Pesquisa', 'status' => 'draft', 'questions' => $this->dummyQuestions, 'author_id' => $director->id]);

        $response = $this->actingAs($director, 'sanctum')->postJson('/api/invitations', [
            'search_id' => $search->id,
            'target_type' => 'manager',
            'strategy' => 'individual',
            'recipient_id' => $masterUser->id
        ]);

        // A regra do InvitationController diz: if ($user->type === 'director' && $target->type !== 'director') return 403;
        $response->assertStatus(403);
    }

    /**
     * Testar o envio de convites em massa por especialidade para respondentes (Médicos).
     */
    public function test_director_can_send_bulk_specialty_invites_to_responders(): void
    {
        $director = User::create(['type' => 'director', 'name' => 'Diretor', 'email' => 'd@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        
        $specPediatria = Specialty::create(['name' => 'Pediatria']);
        $specCardio = Specialty::create(['name' => 'Cardiologia']);
        
        // Criação de Respondentes na tabela correta
        $responderPediatra = Responder::create(['name' => 'Pediatra', 'email' => 'ped@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        $responderPediatra->specialties()->sync([$specPediatria->id]);

        $responderCardio = Responder::create(['name' => 'Cardio', 'email' => 'car@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        $responderCardio->specialties()->sync([$specCardio->id]);

        $search = Search::create(['title' => 'Pesquisa', 'status' => 'draft', 'questions' => $this->dummyQuestions, 'author_id' => $director->id]);

        $response = $this->actingAs($director, 'sanctum')->postJson('/api/invitations', [
            'search_id' => $search->id,
            'target_type' => 'responder',
            'strategy' => 'specialty',
            'specialties' => [$specPediatria->id]
        ]);

        $response->assertStatus(200);

        // Apenas o pediatra deve ter recebido o convite
        $this->assertDatabaseHas('search_invitations', [
            'search_id' => $search->id,
            'email' => $responderPediatra->email
        ]);
        
        $this->assertDatabaseMissing('search_invitations', [
            'search_id' => $search->id,
            'email' => $responderCardio->email
        ]);
    }

    /**
     * Testar envio individual para um respondente específico.
     */
    public function test_director_can_invite_individual_responder(): void
    {
        $director = User::create(['type' => 'director', 'name' => 'Diretor', 'email' => 'd@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        $responder = Responder::create(['name' => 'Doutor', 'email' => 'medico@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        
        $search = Search::create(['title' => 'Pesquisa Institucional AMB', 'status' => 'draft', 'questions' => $this->dummyQuestions, 'author_id' => $director->id]);

        $response = $this->actingAs($director, 'sanctum')->postJson('/api/invitations', [
            'search_id' => $search->id,
            'target_type' => 'responder',
            'strategy' => 'individual',
            'recipient_id' => $responder->id
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('search_invitations', [
            'search_id' => $search->id,
            'email' => $responder->email
        ]);
    }

    /**
     * Testar o ciclo completo do Aceite do convite por um respondente.
     */
    public function test_responder_can_accept_invitation(): void
    {
        $director = User::create(['type' => 'director', 'name' => 'Diretor', 'email' => 'd@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        $responder = Responder::create(['name' => 'Médico', 'email' => 'm@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        $search = Search::create(['title' => 'Pesquisa', 'status' => 'published', 'questions' => $this->dummyQuestions, 'author_id' => $director->id]);

        // Cria convite pendente na tabela de convites de pesquisa
        $invitation = SearchInvitation::create([
            'search_id' => $search->id,
            'sender_id' => $director->id,
            'email' => $responder->email,
            'name' => $responder->name,
            'responder_id' => $responder->id,
            'status' => 'sent',
            'delivery_status' => 'success_email'
        ]);

        // Atua como Respondente e aceita
        $response = $this->actingAs($responder, 'sanctum')->putJson("/api/invitations/{$invitation->id}/accept");

        $response->assertStatus(200);
        
        // Verifica a alteração de status
        $this->assertDatabaseHas('search_invitations', [
            'id' => $invitation->id, 
            'status' => 'accepted'
        ]);
    }
}