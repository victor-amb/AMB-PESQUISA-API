<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Responder;
use App\Models\Specialty;
use App\Models\Search;
use App\Models\SearchInvitation;
use App\Models\SystemInvitation;
use App\Models\SearchAnswer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Define a senha global de testes para 123
        $globalPassword = Hash::make('123');

        $pediatria = Specialty::where('name', 'Pediatria')->first();
        $cardiologia = Specialty::where('name', 'Cardiologia')->first();
        $neurologia = Specialty::where('name', 'Neurologia')->first();

        // ========================================================
        // 1. CRIAÇÃO DE USUÁRIOS ADMINISTRATIVOS (USERS)
        // ========================================================
        
        // 2 Masters (Supremos do sistema)
        $master1 = User::create([
            'type' => 'master', 'name' => 'Dr. Carlos Master (AMB)',
            'email' => 'master@amb.org.br', 'password' => $globalPassword
        ]);
        User::create([
            'type' => 'master', 'name' => 'Dra. Ana Master (AMB)',
            'email' => 'master2@amb.org.br', 'password' => $globalPassword
        ]);

        // 8 Diretores com cenários variados de especialidades
        $dirPediatria = User::create([
            'type' => 'director', 'name' => 'Diretor da Pediatria',
            'email' => 'dir.pediatria@amb.org.br', 'password' => $globalPassword, 'inviter_id' => $master1->id
        ]);
        if ($pediatria) $dirPediatria->specialties()->attach($pediatria->id);

        $dirCardio = User::create([
            'type' => 'director', 'name' => 'Diretor da Cardiologia',
            'email' => 'dir.cardio@amb.org.br', 'password' => $globalPassword
        ]);
        if ($cardiologia) $dirCardio->specialties()->attach($cardiologia->id);

        $dirMulti = User::create([
            'type' => 'director', 'name' => 'Diretor Multi-Sociedades (Ped/Neuro)',
            'email' => 'dir.multi@amb.org.br', 'password' => $globalPassword
        ]);
        if ($pediatria && $neurologia) $dirMulti->specialties()->attach([$pediatria->id, $neurologia->id]);

        // Diretores Institucionais sem especialidade atrelada (Foco Global)
        $dirGlobal1 = User::create([
            'type' => 'director', 'name' => 'Diretor de Pesquisas Globais A',
            'email' => 'dir.globala@amb.org.br', 'password' => $globalPassword
        ]);
        $dirGlobal2 = User::create([
            'type' => 'director', 'name' => 'Diretor de Pesquisas Globais B',
            'email' => 'dir.globalb@amb.org.br', 'password' => $globalPassword
        ]);

        // Outros diretores para complementar o ecossistema (8 no total)
        for ($i = 1; $i <= 3; $i++) {
            User::create([
                'type' => 'director', 'name' => "Diretor Auxiliar {$i}",
                'email' => "diretor.aux{$i}@amb.org.br", 'password' => $globalPassword
            ]);
        }

        // ========================================================
        // 2. CRIAÇÃO DE PESQUISAS (CENÁRIOS VARIADOS)
        // ========================================================
        
        $questionsTemplate = [
            ['id' => 'q1', 'type' => 'text', 'label' => 'Qual sua avaliação do cenário atual?', 'required' => true, 'order' => 1],
            ['id' => 'q2', 'type' => 'radio', 'label' => 'Satisfeito?', 'options' => ['Sim', 'Não'], 'required' => true, 'order' => 2]
        ];

        // Pesquisa 1: Segmentada (Pediatria) - Publicada
        $searchPediatria = Search::create([
            'title' => 'Censo Nacional de Pediatria 2026', 'questions' => $questionsTemplate,
            'status' => 'published', 'author_id' => $dirPediatria->id, 'start_date' => now()->subDays(5)
        ]);
        if ($pediatria) $searchPediatria->specialties()->attach($pediatria->id);

        // Pesquisa 2: Sequencial (Etapa 2 da Pediatria) - Rascunho
        $searchPediatriaEtapa2 = Search::create([
            'title' => 'Impacto da Amamentação - Etapa 2 (Desdobramento)', 'questions' => $questionsTemplate,
            'status' => 'draft', 'author_id' => $dirPediatria->id, 'parent_search_id' => $searchPediatria->id
        ]);

        // Pesquisa 3: Global/Institucional (Sem Especialidade) - Publicada
        $searchGlobal = Search::create([
            'title' => 'Pesquisa de Saúde Mental Médica AMB', 'questions' => $questionsTemplate,
            'status' => 'published', 'author_id' => $dirGlobal1->id, 'start_date' => now()->subDays(2)
        ]);

        // Pesquisa 4: Co-gerenciada (Criada por Cardio, gerenciada também por Multi)
        $searchCardioCo = Search::create([
            'title' => 'Estudo de Caso Coração Infantil', 'questions' => $questionsTemplate,
            'status' => 'published', 'author_id' => $dirCardio->id
        ]);
        $searchCardioCo->managers()->attach($dirMulti->id);


        // ========================================================
        // 3. CRIAÇÃO DE RESPONDENTES E SEUS STATUS DE ENGAJAMENTO
        // ========================================================

        // Cenário 1: Médicos Orgânicos da Base (Com especialidade, sem inviter_id)
        $medPediatria1 = Responder::create([
            'name' => 'Dr. Roberto Pediatra', 'email' => 'roberto@medico.com',
            'password' => $globalPassword, 'crm' => 12345, 'crm_state' => 'SP'
        ]);
        if ($pediatria) $medPediatria1->specialties()->attach($pediatria->id);

        $medPediatria2 = Responder::create([
            'name' => 'Dra. Julia Pediatra', 'email' => 'julia@medico.com',
            'password' => $globalPassword, 'crm' => 54321, 'crm_state' => 'RJ'
        ]);
        if ($pediatria) $medPediatria2->specialties()->attach($pediatria->id);

        $medCardio = Responder::create([
            'name' => 'Dr. Marcos Cardiologista', 'email' => 'marcos@medico.com',
            'password' => $globalPassword, 'crm' => 98765, 'crm_state' => 'MG'
        ]);
        if ($cardiologia) $medCardio->specialties()->attach($cardiologia->id);

        // Cenário 2: Respondente Não-Médico (Sem CRM, dados dinâmicos salvos em metadata)
        $enfermeiroGlobal = Responder::create([
            'name' => 'Aline Enfermeira Comum', 'email' => 'aline@enfermagem.com',
            'password' => $globalPassword, 'crm' => null, 'crm_state' => null,
            'metadata' => ['profissao' => 'Enfermagem', 'instituicao' => 'SUS']
        ]);

        // Cenário 3: Respondentes vindos de fora por Planilha JÁ vinculados a uma pesquisa específica
        $importadoPesquisa = Responder::create([
            'name' => 'Dr. Fernando Importado (Mailing)', 'email' => 'fernando.importado@externo.com',
            'password' => $globalPassword, 'crm' => 77777, 'crm_state' => 'PR',
            'inviter_id' => $dirPediatria->id, 'metadata' => ['origem_planilha' => 'Lote_Pediatria_Sul_CSV']
        ]);
        if ($pediatria) $importadoPesquisa->specialties()->attach($pediatria->id);

        // Vincula o convite específico dele na pesquisa 1 (Ele aceitou participar)
        SearchInvitation::create([
            'search_id' => $searchPediatria->id, 'sender_id' => $dirPediatria->id,
            'email' => $importadoPesquisa->email, 'name' => $importadoPesquisa->name,
            'status' => 'accepted', 'delivery_status' => 'success_email', 'responder_id' => $importadoPesquisa->id
        ]);

        // Cenário 4: Importado em massa para o sistema, mas SEM vínculo inicial a nenhuma pesquisa
        $importadoSistemaApenas = Responder::create([
            'name' => 'Dra. Sandra Importada Geral', 'email' => 'sandra.geral@externo.com',
            'password' => $globalPassword, 'crm' => 88888, 'crm_state' => 'SC',
            'inviter_id' => $master1->id, 'metadata' => ['origem_planilha' => 'Mailing_Geral_AMB_2026']
        ]);

        // Registra a entrada dela na tabela de convites do ecossistema geral
        SystemInvitation::create([
            'sender_id' => $master1->id, 'name' => $importadoSistemaApenas->name,
            'email' => $importadoSistemaApenas->email, 'status' => 'registered'
        ]);


        // ========================================================
        // 4. ALIMENTANDO O FUNIL DE RESPOSTAS E PROGRES_STATUS
        // ========================================================

        // A) Cenário: Respondente que já CONCLUIU a pesquisa
        SearchAnswer::create([
            'search_id' => $searchPediatria->id, 'responder_id' => $medPediatria1->id,
            'progress_status' => 'completed', 'answers' => ['q1' => 'Cenário desafiador', 'q2' => 'Não'],
            'started_at' => now()->subHours(2), 'completed_at' => now()->subHours(1)
        ]);
        SearchInvitation::create([
            'search_id' => $searchPediatria->id, 'sender_id' => $dirPediatria->id,
            'email' => $medPediatria1->email, 'name' => $medPediatria1->name,
            'status' => 'accepted', 'delivery_status' => 'success_email', 'responder_id' => $medPediatria1->id
        ]);

        // B) Cenário: Respondente que COMEÇOU mas NÃO TERMINOU (Rascunho / In Progress)
        SearchAnswer::create([
            'search_id' => $searchPediatria->id, 'responder_id' => $medPediatria2->id,
            'progress_status' => 'in_progress', 'answers' => ['q1' => 'Mudando para melhor, aguardando dados...'],
            'started_at' => now()->subMinutes(30), 'completed_at' => null
        ]);
        SearchInvitation::create([
            'search_id' => $searchPediatria->id, 'sender_id' => $dirPediatria->id,
            'email' => $medPediatria2->email, 'name' => $medPediatria2->name,
            'status' => 'sent', 'delivery_status' => 'success_email', 'responder_id' => $medPediatria2->id
        ]);

        // C) Cenário: Foi convidado para a pesquisa Global mas NEM COMEÇOU a responder (Fila Pura)
        SearchInvitation::create([
            'search_id' => $searchGlobal->id, 'sender_id' => $dirGlobal1->id,
            'email' => $enfermeiroGlobal->email, 'name' => $enfermeiroGlobal->name,
            'status' => 'sent', 'delivery_status' => 'success_whatsapp', 'responder_id' => $enfermeiroGlobal->id
        ]);
    }
}