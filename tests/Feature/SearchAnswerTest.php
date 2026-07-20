<?php

namespace Tests\Feature;

use App\Models\Search;
use App\Models\SearchAnswer;
use App\Models\Specialty;
use App\Models\User;
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
        $doctor = User::create(['type' => 'common', 'name' => 'Dr. Silva', 'email' => 'silva@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        $master = User::create(['type' => 'master', 'name' => 'Admin', 'email' => 'm@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        
        $search = Search::create(['title' => 'Pesquisa Climatologia', 'status' => 'published', 'questions' => $this->dummyQuestions, 'author_id' => $master->id]);

        $response = $this->actingAs($doctor, 'sanctum')->postJson('/api/answers', [
            'search_id' => $search->id,
            'answers' => $this->dummyAnswers
        ]);

        $response->assertStatus(201)->assertJsonPath('message', 'Sua participação foi registrada com sucesso! Obrigado.');
        $this->assertDatabaseHas('search_answers', ['search_id' => $search->id, 'user_id' => $doctor->id]);
    }

    /**
     * Testar impedimento de resposta se a pesquisa não estiver publicada.
     */
    public function test_doctor_cannot_answer_unpublished_search(): void
    {
        $doctor = User::create(['type' => 'common', 'name' => 'Dr. Silva', 'email' => 'silva@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        $master = User::create(['type' => 'master', 'name' => 'Admin', 'email' => 'm@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        
        $search = Search::create(['title' => 'Pesquisa Secreta', 'status' => 'draft', 'questions' => $this->dummyQuestions, 'author_id' => $master->id]);

        $response = $this->actingAs($doctor, 'sanctum')->postJson('/api/answers', [
            'search_id' => $search->id,
            'answers' => $this->dummyAnswers
        ]);

        $response->assertStatus(422); // Rejeitado pelas regras de status
    }

    /**
     * Testar a Regra de Ouro: Bloqueio de duplicidade de resposta (Voto Único).
     */
    public function test_doctor_cannot_answer_the_same_search_twice(): void
    {
        $doctor = User::create(['type' => 'common', 'name' => 'Dr. Silva', 'email' => 'silva@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        $master = User::create(['type' => 'master', 'name' => 'Admin', 'email' => 'm@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        
        $search = Search::create(['title' => 'Pesquisa Cardiologia', 'status' => 'published', 'questions' => $this->dummyQuestions, 'author_id' => $master->id]);

        // Cria a primeira resposta de forma estática no banco
        SearchAnswer::create(['search_id' => $search->id, 'user_id' => $doctor->id, 'answers' => $this->dummyAnswers]);

        // Tenta enviar uma segunda resposta via API
        $response = $this->actingAs($doctor, 'sanctum')->postJson('/api/answers', [
            'search_id' => $search->id,
            'answers' => $this->dummyAnswers
        ]);

        $response->assertStatus(409); // Conflict (Já respondeu)
    }

    /**
     * Testar Fila de Pendências: Traz pesquisas da especialidade ou globais solicitadas que ele não respondeu.
     */
    public function test_pending_searches_endpoint_filters_correctly(): void
    {
        $doctor = User::create(['type' => 'common', 'name' => 'Dr. Silva', 'email' => 'silva@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        $spec = Specialty::create(['name' => 'Pediatria']);
        $doctor->specialties()->sync([$spec->id]);

        $master = User::create(['type' => 'master', 'name' => 'Admin', 'email' => 'm@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);

        // 1. Pesquisa da especialidade dele (Deve aparecer nas pendências)
        $s1 = Search::create(['title' => 'Pesquisa Pediatria 1', 'status' => 'published', 'questions' => $this->dummyQuestions, 'author_id' => $master->id]);
        $s1->specialties()->sync([$spec->id]);

        // 2. Pesquisa da especialidade dele, mas que ele JÁ respondeu (Não deve aparecer)
        $s2 = Search::create(['title' => 'Pesquisa Pediatria 2', 'status' => 'published', 'questions' => $this->dummyQuestions, 'author_id' => $master->id]);
        $s2->specialties()->sync([$spec->id]);
        SearchAnswer::create(['search_id' => $s2->id, 'user_id' => $doctor->id, 'answers' => $this->dummyAnswers]);

        // 3. Pesquisa Global direcionada/solicitada a ele via targets (Deve aparecer nas pendências)
        $s3 = Search::create(['title' => 'Global Solicitada', 'status' => 'published', 'questions' => $this->dummyQuestions, 'author_id' => $master->id]);
        $s3->targets()->attach($doctor->id);

        // 4. Pesquisa Global solta sem direcionamento para ele (Não deve aparecer)
        Search::create(['title' => 'Global Geral', 'status' => 'published', 'questions' => $this->dummyQuestions, 'author_id' => $master->id]);

        $response = $this->actingAs($doctor, 'sanctum')->getJson('/api/my-searches/pending');

        $response->assertStatus(200)->assertJsonCount(2, 'data'); // Apenas s1 e s3 pendem
    }

    /**
     * Testar Histórico: Listar apenas questionários já respondidos.
     */
    public function test_answered_searches_endpoint_returns_only_participated_searches(): void
    {
        $doctor = User::create(['type' => 'common', 'name' => 'Dr. Silva', 'email' => 'silva@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        $master = User::create(['type' => 'master', 'name' => 'Admin', 'email' => 'm@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);

        $s1 = Search::create(['title' => 'Respondida com orgulho', 'status' => 'published', 'questions' => $this->dummyQuestions, 'author_id' => $master->id]);
        Search::create(['title' => 'Ignorada/Pendente', 'status' => 'published', 'questions' => $this->dummyQuestions, 'author_id' => $master->id]);

        // Registra a resposta para a primeira pesquisa
        SearchAnswer::create(['search_id' => $s1->id, 'user_id' => $doctor->id, 'answers' => $this->dummyAnswers]);

        $response = $this->actingAs($doctor, 'sanctum')->getJson('/api/my-searches/answered');

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data')
                 ->assertJsonPath('data.0.title', 'Respondida com orgulho');
    }
}