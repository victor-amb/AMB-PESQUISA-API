<?php

namespace Tests\Feature;

use App\Models\Search;
use App\Models\SearchAnswer;
use App\Models\SearchInvitation;
use App\Models\Specialty;
use App\Models\User;
use App\Models\Responder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchAnswerTest extends TestCase
{
    use RefreshDatabase;

    private array $dummyQuestions = [['id' => 'q1', 'type' => 'text', 'label' => 'Pergunta', 'required' => true, 'order' => 1]];
    private array $dummyAnswers = [['question_id' => 'q1', 'value' => 'Resposta do médico']];

    /**
     * Testar proteção de rotas da área do médico.
     */
    public function test_doctor_endpoints_require_authentication(): void
    {
        $this->postJson('/api/answers', [])->assertStatus(401);
        $this->getJson('/api/my-searches/answered')->assertStatus(401);
        $this->getJson('/api/my-searches/pending')->assertStatus(401);
    }

    /**
     * Testar submissão de resposta com sucesso.
     */
    public function test_doctor_can_submit_answers_to_published_search(): void
    {
        $doctor = Responder::create(['name' => 'Dr. Silva', 'email' => 'silva@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        $master = User::create(['type' => 'master', 'name' => 'Admin', 'email' => 'm@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);

        $search = Search::create(['title' => 'Pesquisa Climatologia', 'status' => 'published', 'questions' => $this->dummyQuestions, 'author_id' => $master->id]);

        $response = $this->actingAs($doctor, 'sanctum')->postJson('/api/answers', [
            'search_id' => $search->id,
            'answers' => $this->dummyAnswers,
            'progress_status' => 'completed' // Necessário para marcar como finalizada
        ]);

        // Controller retorna 200 e a mensagem atualizada
        $response->assertStatus(200)->assertJsonPath('message', 'Sua participação foi registrada e finalizada com sucesso! Obrigado.');

        $this->assertDatabaseHas('search_answers', [
            'search_id' => $search->id,
            'responder_id' => $doctor->id,
            'progress_status' => 'completed'
        ]);
    }

    /**
     * Testar impedimento de resposta se a pesquisa não estiver publicada.
     */
    public function test_doctor_cannot_answer_unpublished_search(): void
    {
        $doctor = Responder::create(['name' => 'Dr. Silva', 'email' => 'silva@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        $master = User::create(['type' => 'master', 'name' => 'Admin', 'email' => 'm@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);

        $search = Search::create(['title' => 'Pesquisa Secreta', 'status' => 'draft', 'questions' => $this->dummyQuestions, 'author_id' => $master->id]);

        $response = $this->actingAs($doctor, 'sanctum')->postJson('/api/answers', [
            'search_id' => $search->id,
            'answers' => $this->dummyAnswers
        ]);

        $response->assertStatus(422);
    }

    /**
     * Testar a Regra de Ouro: Bloqueio de duplicidade de resposta (Voto Único).
     */
    public function test_doctor_cannot_answer_the_same_search_twice(): void
    {
        $doctor = Responder::create(['name' => 'Dr. Silva', 'email' => 'silva@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        $master = User::create(['type' => 'master', 'name' => 'Admin', 'email' => 'm@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);

        $search = Search::create(['title' => 'Pesquisa Cardiologia', 'status' => 'published', 'questions' => $this->dummyQuestions, 'author_id' => $master->id]);

        // Mockamos a resposta no banco JÁ COMPLETADA
        SearchAnswer::create([
            'search_id' => $search->id,
            'responder_id' => $doctor->id,
            'answers' => $this->dummyAnswers,
            'progress_status' => 'completed'
        ]);

        // Tenta enviar via API de novo
        $response = $this->actingAs($doctor, 'sanctum')->postJson('/api/answers', [
            'search_id' => $search->id,
            'answers' => $this->dummyAnswers,
            'progress_status' => 'completed'
        ]);

        $response->assertStatus(409); // Conflict
    }

    /**
     * Testar Fila de Pendências: Todas as pesquisas exigem convite formal (SearchInvitation)
     */
    public function test_pending_searches_endpoint_filters_correctly(): void
    {
        $doctor = Responder::create(['name' => 'Dr. Silva', 'email' => 'silva@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        $master = User::create(['type' => 'master', 'name' => 'Admin', 'email' => 'm@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);

        // 1. Pesquisa COM CONVITE ACEITO (Deve aparecer nas pendências)
        $s1 = Search::create(['title' => 'Pesquisa Convidada 1', 'status' => 'published', 'questions' => $this->dummyQuestions, 'author_id' => $master->id]);
        SearchInvitation::create([
            'search_id' => $s1->id,
            'email' => $doctor->email,
            'status' => 'accepted', // Regra: Convite aceito!
            'sender_id' => $master->id
        ]);

        // 2. Pesquisa COM CONVITE ACEITO, mas que ele JÁ COMPLETOU (Não deve aparecer nas pendências)
        $s2 = Search::create(['title' => 'Pesquisa Convidada 2', 'status' => 'published', 'questions' => $this->dummyQuestions, 'author_id' => $master->id]);
        SearchInvitation::create([
            'search_id' => $s2->id,
            'email' => $doctor->email,
            'status' => 'accepted',
            'sender_id' => $master->id
        ]);
        SearchAnswer::create([
            'search_id' => $s2->id,
            'responder_id' => $doctor->id,
            'answers' => $this->dummyAnswers,
            'progress_status' => 'completed'
        ]);

        // 3. Outra Pesquisa COM CONVITE ACEITO (Deve aparecer nas pendências)
        $s3 = Search::create(['title' => 'Global Solicitada', 'status' => 'published', 'questions' => $this->dummyQuestions, 'author_id' => $master->id]);
        SearchInvitation::create([
            'search_id' => $s3->id,
            'email' => $doctor->email,
            'status' => 'accepted', // Regra: Convite aceito!
            'sender_id' => $master->id
        ]);

        // 4. Pesquisa com convite PENDENTE/SENT (Não deve aparecer até ser aceito)
        $s4 = Search::create(['title' => 'Pesquisa Pendente de Aceite', 'status' => 'published', 'questions' => $this->dummyQuestions, 'author_id' => $master->id]);
        SearchInvitation::create([
            'search_id' => $s4->id,
            'email' => $doctor->email,
            'status' => 'sent',
            'sender_id' => $master->id
        ]);

        // 5. Pesquisa SEM CONVITE NENHUM (Não deve aparecer)
        Search::create(['title' => 'Global Sem Convite', 'status' => 'published', 'questions' => $this->dummyQuestions, 'author_id' => $master->id]);

        $response = $this->actingAs($doctor, 'sanctum')->getJson('/api/my-searches/pending');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data') // Apenas s1 e s3 (apenas convites 'accepted' e não finalizados)
            ->assertJsonFragment(['title' => 'Pesquisa Convidada 1'])
            ->assertJsonFragment(['title' => 'Global Solicitada'])
            ->assertJsonMissing(['title' => 'Pesquisa Pendente de Aceite'])
            ->assertJsonMissing(['title' => 'Global Sem Convite']);
    }

    /**
     * Testar Histórico: Listar apenas questionários já respondidos.
     */
    public function test_answered_searches_endpoint_returns_only_participated_searches(): void
    {
        $doctor = Responder::create(['name' => 'Dr. Silva', 'email' => 'silva@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        $master = User::create(['type' => 'master', 'name' => 'Admin', 'email' => 'm@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);

        $s1 = Search::create(['title' => 'Respondida com orgulho', 'status' => 'published', 'questions' => $this->dummyQuestions, 'author_id' => $master->id]);
        Search::create(['title' => 'Ignorada/Pendente', 'status' => 'published', 'questions' => $this->dummyQuestions, 'author_id' => $master->id]);

        // Resposta precisa ser criada com status 'completed'
        SearchAnswer::create([
            'search_id' => $s1->id,
            'responder_id' => $doctor->id,
            'answers' => $this->dummyAnswers,
            'progress_status' => 'completed'
        ]);

        $response = $this->actingAs($doctor, 'sanctum')->getJson('/api/my-searches/answered');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Respondida com orgulho');
    }
}