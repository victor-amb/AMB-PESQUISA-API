<?php

namespace Tests\Feature;

use App\Models\Search;
use App\Models\SearchAnswer;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Responder;
use App\Models\SearchInvitation;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    private array $defaultQuestions = [
        [
            'id' => 'q1',
            'type' => 'text',
            'label' => 'Qual o seu nome?',
            'required' => true,
            'order' => 1
        ]
    ];

    /**
     * Testar se as rotas de Pesquisa exigem autenticação.
     */
    public function test_search_routes_require_authentication(): void
    {
        $this->getJson('/api/searches')->assertStatus(401);
        $this->postJson('/api/searches', [])->assertStatus(401);
        $this->putJson('/api/searches/1', [])->assertStatus(401);
        $this->deleteJson('/api/searches/1')->assertStatus(401);
    }

    /**
     * Regra Listagem (Master): Vê absolutamente tudo.
     */
    public function test_master_user_can_list_all_searches(): void
    {
        $master = User::create(['type' => 'master', 'name' => 'Admin', 'email' => 'm@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);

        // Criando os autores reais no banco para validar a chave estrangeira
        $author1 = User::create(['type' => 'director', 'name' => 'Autor 1', 'email' => 'author1@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        $author2 = User::create(['type' => 'director', 'name' => 'Autor 2', 'email' => 'author2@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);

        // Passamos os IDs dinâmicos gerados pelo banco
        Search::create(['title' => 'P1', 'status' => 'draft', 'questions' => $this->defaultQuestions, 'author_id' => $author1->id]);
        Search::create(['title' => 'P2', 'status' => 'published', 'questions' => $this->defaultQuestions, 'author_id' => $author2->id]);

        $response = $this->actingAs($master, 'sanctum')->getJson('/api/searches');

        $response->assertStatus(200)->assertJsonCount(2, 'data');
    }

    /**
     * Regra Listagem (Diretor): Vê apenas as criadas por ele ou que foi convidado a gerenciar.
     */
    public function test_director_can_only_list_their_own_or_managed_searches(): void
    {
        $director = User::create(['type' => 'director', 'name' => 'Diretor 1', 'email' => 'd1@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        $otherUser = User::create(['type' => 'director', 'name' => 'Diretor 2', 'email' => 'd2@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);

        // 1. Pesquisa criada por ele
        Search::create(['title' => 'Minha Pesquisa', 'status' => 'draft', 'questions' => $this->defaultQuestions, 'author_id' => $director->id]);

        // 2. Pesquisa de outro, mas que ele é manager (coparticipante)
        $managedSearch = Search::create(['title' => 'Pesquisa Compartilhada', 'status' => 'draft', 'questions' => $this->defaultQuestions, 'author_id' => $otherUser->id]);
        $managedSearch->managers()->attach($director->id);

        // 3. Pesquisa de outro que ele não tem acesso
        Search::create(['title' => 'Pesquisa Oculta', 'status' => 'draft', 'questions' => $this->defaultQuestions, 'author_id' => $otherUser->id]);

        $response = $this->actingAs($director, 'sanctum')->getJson('/api/searches');

        $response->assertStatus(200)->assertJsonCount(2, 'data');
    }

    /**
     * Regra Criação: Diretor pode criar pesquisa para as especialidades dele OU global.
     */
    public function test_director_can_create_search_in_their_specialty_or_global(): void
    {
        $director = User::create(['type' => 'director', 'name' => 'Diretor', 'email' => 'd@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        $spec = Specialty::create(['name' => 'Pediatria']);
        $director->specialties()->sync([$spec->id]);

        // 1. Criando pesquisa com a sua especialidade
        $response1 = $this->actingAs($director, 'sanctum')->postJson('/api/searches', [
            'title' => 'Pesquisa Pediatria',
            'status' => 'draft',
            'specialties' => [$spec->id],
            'questions' => $this->defaultQuestions
        ]);
        $response1->assertStatus(201);

        // 2. Criando pesquisa Global (sem especialidades) - Permitido conforme nova regra de autonomia
        $response2 = $this->actingAs($director, 'sanctum')->postJson('/api/searches', [
            'title' => 'Pesquisa Institucional Global',
            'status' => 'draft',
            'specialties' => [],
            'questions' => $this->defaultQuestions
        ]);
        $response2->assertStatus(201);
    }

    /**
     * Regra Criação: Diretor é bloqueado se tentar associar uma especialidade que ele não gerencia.
     */
    public function test_director_cannot_create_search_for_unmanaged_specialty(): void
    {
        $director = User::create(['type' => 'director', 'name' => 'Diretor', 'email' => 'd@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        $specBelongs = Specialty::create(['name' => 'Pediatria']);
        $specOther = Specialty::create(['name' => 'Psiquiatria']);

        $director->specialties()->sync([$specBelongs->id]);

        $response = $this->actingAs($director, 'sanctum')->postJson('/api/searches', [
            'title' => 'Pesquisa Invasora',
            'status' => 'draft',
            'specialties' => [$specOther->id],
            'questions' => $this->defaultQuestions
        ]);

        $response->assertStatus(403);
    }

    /**
     * Regra de Ouro (Atualização): Permite alterar perguntas apenas se for Draft e não tiver respostas.
     */
    public function test_cannot_update_questions_if_search_is_published_or_has_answers(): void
    {
        $master = User::create(['type' => 'master', 'name' => 'Admin', 'email' => 'm@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        $doctor = Responder::create(['name' => 'Médico Respondedor', 'email' => 'doc2@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);

        // 1. Pesquisa já publicada
        $searchPublished = Search::create(['title' => 'P1', 'status' => 'published', 'questions' => $this->defaultQuestions, 'author_id' => $master->id]);

        $response1 = $this->actingAs($master, 'sanctum')->putJson("/api/searches/{$searchPublished->id}", [
            'title' => 'P1 Alterada',
            'status' => 'published',
            'questions' => [
                ['id' => 'q1', 'type' => 'text', 'label' => 'Nova pergunta inserida indevidamente', 'required' => true, 'order' => 1]
            ]
        ]);
        $response1->assertStatus(422); // Estrutura travada

        // 2. Pesquisa em Draft, mas que já possui respostas coletadas
        $searchDraftWithAnswers = Search::create(['title' => 'P2', 'status' => 'draft', 'questions' => $this->defaultQuestions, 'author_id' => $master->id]);
        SearchAnswer::create([
            'search_id' => $searchDraftWithAnswers->id,
            'responder_id' => $doctor->id, // 🌟 CORREÇÃO: Usa responder_id
            'answers' => ['q1' => 'Resposta Exemplo']
        ]);

        $response2 = $this->actingAs($master, 'sanctum')->putJson("/api/searches/{$searchDraftWithAnswers->id}", [
            'title' => 'P2 Título Alterado',
            'status' => 'draft',
            'questions' => [
                ['id' => 'q1', 'type' => 'text', 'label' => 'Tentativa de quebrar integridade', 'required' => true, 'order' => 1]
            ]
        ]);
        $response2->assertStatus(422); // Bloqueia por já possuir histórico científico
    }

    /**
     * Regra Excluir: Bloqueia a exclusão física se a pesquisa já possuir respostas coletadas.
     */
    public function test_cannot_delete_search_if_it_has_answers(): void
    {
        $master = User::create(['type' => 'master', 'name' => 'Admin', 'email' => 'm@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        
        // 🌟 CORREÇÃO: Médico agora é Responder!
        $doctor = Responder::create(['name' => 'Médico Respondedor', 'email' => 'doc3@amb.com.br', 'password' => bcrypt('123'), 'active' => true]);
        
        $search = Search::create(['title' => 'Pesquisa com histórico', 'status' => 'published', 'questions' => $this->defaultQuestions, 'author_id' => $master->id]);
        
        SearchAnswer::create([
            'search_id' => $search->id,
            'responder_id' => $doctor->id, // 🌟 CORREÇÃO: Usa responder_id
            'answers' => ['q1' => 'Sim']
        ]);

        $response = $this->actingAs($master, 'sanctum')->deleteJson("/api/searches/{$search->id}");

        $response->assertStatus(409); // Conflito de integridade de dados
        $this->assertDatabaseHas('searches', ['id' => $search->id]); // Continua salva no banco
    }
}