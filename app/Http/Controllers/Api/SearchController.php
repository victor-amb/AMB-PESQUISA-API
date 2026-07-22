<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSearchRequest;
use App\Models\Search;
use App\Models\SearchAnswer;
use App\Models\SearchInvitation;
use App\Models\User;
use App\Models\Responder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    /**
     * Listar Pesquisas (Com Motor de Filtros)
     * @tags Pesquisas
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Search::with(['specialties', 'author:id,name']);

        // =========================================================
        // 1. BLINDAGEM DE ESCOPO (Regra de acesso por Perfil)
        // =========================================================
        if ($user instanceof Responder) {
            $userSpecialties = $user->specialties->pluck('id')->toArray();
            $query->where('status', 'published')
                ->where(function ($q) use ($userSpecialties, $user) {
                    $q->whereHas('specialties', fn($sq) => $sq->whereIn('specialties.id', $userSpecialties))
                        ->orWhere(function ($sq) use ($user) {
                            $sq->doesntHave('specialties')->whereHas('invitations', function ($iq) use ($user) {
                                $iq->where('responder_id', $user->id)->whereIn('status', ['sent', 'accepted']);
                            });
                        });
                });
        } elseif ($user instanceof User && $user->type === 'director') {
            $query->where(fn($q) => $q->where('author_id', $user->id)->orWhereHas('managers', fn($sq) => $sq->where('users.id', $user->id)));
        }

        // =========================================================
        // 🌟 2. MOTOR DE FILTROS APLICADOS PELO FRONTEND
        // =========================================================

        // Filtro: Pesquisa Rápida (Título ou Descrição)
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'like', "%{$searchTerm}%")
                  ->orWhere('description', 'like', "%{$searchTerm}%");
            });
        }

        // Filtro: Status da Coleta
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filtro: Segmentação (Global vs Segmentada)
        if ($request->filled('scope') && $request->scope !== 'all') {
            if ($request->scope === 'global') {
                $query->doesntHave('specialties');
            } elseif ($request->scope === 'segmented') {
                $query->has('specialties');
            }
        }

        // Filtro: Especialidades Relacionadas
        if ($request->filled('specialties')) {
            // O frontend (Axios) pode serializar arrays de formas diferentes (array nativo ou string separada por vírgula)
            $specialties = is_array($request->specialties) 
                ? $request->specialties 
                : explode(',', $request->specialties);

            if (count($specialties) > 0) {
                $query->whereHas('specialties', function ($q) use ($specialties) {
                    $q->whereIn('specialties.id', $specialties);
                });
            }
        }

        if ($request->filled('creator_id') && $request->creator_id !== 'all') {
            $query->where('author_id', $request->creator_id);
        }

        // Filtro: Período (Preparado para expansão futura do frontend)
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        return response()->json($query->latest()->paginate(15));
    }

/**
     * Salvar Nova Pesquisa
     * @tags Pesquisas
     */
    public function store(StoreSearchRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $data['author_id'] = $user->id;
        $search = Search::create($data);

        // 🌟 NOVO: Salva o criador (autor) na tabela search_managers
        $search->managers()->attach($user->id);

        if (!empty($data['specialties'])) {
            $search->specialties()->sync($data['specialties']);
        }

        return response()->json(['message' => 'Pesquisa criada com sucesso!', 'search' => $search->load('specialties')], 201);
    }

    /**
     * Detalhar Pesquisa
     * @tags Pesquisas
     */
    public function show(Request $request, $id): JsonResponse
    {
        $search = Search::with(['specialties', 'author:id,name'])->findOrFail($id);
        return response()->json($search);
    }

    /**
     * Atualizar Pesquisa e Estrutura
     * @tags Pesquisas
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $search = Search::findOrFail($id);
        $oldStatus = $search->status;

        // Regras base (só o que pode vir nulo ou opcional mesmo)
        $rules = [
            'specialties' => 'nullable|array',
            'questions' => 'nullable|array',
        ];

        // 🌟 CORREÇÃO DEFINITIVA: Só adiciona a regra de Título e Status SE o frontend enviá-los!
        if ($request->has('title')) {
            $rules['title'] = 'required|string|max:255';
        }

        if ($request->has('status')) {
            $rules['status'] = 'required|in:draft,published,closed,archived';
        }

        // Proteção: As datas SÓ são processadas se vierem da etapa "Agendamento" ou "Adiar"
        if ($request->boolean('is_scheduling')) {
            $rules['start_date'] = 'nullable|date';
            $rules['end_date'] = 'nullable|date|after:start_date';

            if ($request->filled('start_date')) {
                $dbStart = $search->start_date ? date('Y-m-d H:i', strtotime($search->start_date)) : null;
                $reqStart = date('Y-m-d H:i', strtotime($request->start_date));

                if ($dbStart !== $reqStart) {
                    $rules['start_date'] .= '|after_or_equal:now';
                }
            }
        }

        $data = $request->validate($rules);

        $search->update($data);

        // Só sincroniza as especialidades se o array foi de fato enviado na requisição
        if ($request->has('specialties')) {
            $search->specialties()->sync($data['specialties'] ?? []);
        }

        if ($oldStatus !== 'published' && $search->status === 'published') {
            app(InvitationController::class)->broadcastSearchLaunch($search);
        }

        return response()->json(['message' => 'Pesquisa atualizada com sucesso!', 'search' => $search]);
    }

    /**
     * Deletar Pesquisa
     * @tags Pesquisas
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $search = Search::findOrFail($id);

        if ($search->answers()->exists()) {
            return response()->json(['message' => 'Esta pesquisa possui dados. Altere o status para "arquivada".'], 409);
        }

        DB::transaction(function () use ($search) {
            $search->specialties()->detach();
            $search->managers()->detach();
            $search->invitations()->delete();
            $search->delete();
        });

        return response()->json(['message' => 'Pesquisa excluída com sucesso.']);
    }

    /**
     * Obter Estatísticas Gerais da Pesquisa
     * @tags Pesquisas
     */
    public function getStats(Request $request, $id): JsonResponse
    {
        $search = Search::findOrFail($id);
        return response()->json([
            'total_targets' => $search->invitations()->count(),
            'completed_answers' => SearchAnswer::where('search_id', $id)->where('progress_status', 'completed')->count()
        ]);
    }

    /**
     * Obter Estatísticas Operacionais da Pesquisa
     */
    public function stats($id): JsonResponse
    {
        $search = Search::findOrFail($id);

        // Contagens baseadas nos convites empilhados e respostas recebidas
        $totalConvidados = SearchInvitation::where('search_id', $id)->count();
        $totalAceitos = SearchInvitation::where('search_id', $id)->where('status', 'accepted')->count();

        $respostasConcluidas = SearchAnswer::where('search_id', $id)->where('progress_status', 'completed')->count();
        $respostasAndamento = SearchAnswer::where('search_id', $id)->where('progress_status', 'in_progress')->count();
        $respostasGerais = $respostasConcluidas + $respostasAndamento;

        return response()->json([
            'summary' => [
                'invited' => $totalConvidados,
                'accepted' => $totalAceitos,
                'completed' => $respostasConcluidas,
                'in_progress' => $respostasAndamento,
                'total_answers' => $respostasGerais,
            ],
            'search' => [
                'status' => $search->status,
                'start_date' => $search->start_date ?? $search->created_at,
                'end_date' => $search->end_date
            ]
        ]);
    }

    /**
     * Exportar Dados Brutos de Respostas (Simulador de CSV Estruturado)
     */
    public function exportAnswers($id): JsonResponse
    {
        $search = Search::findOrFail($id);

        $answers = SearchAnswer::where('search_id', $id)
            ->join('responders', 'search_answers.responder_id', '=', 'responders.id')
            ->select('responders.name', 'responders.email', 'responders.crm', 'search_answers.progress_status', 'search_answers.answers', 'search_answers.updated_at')
            ->get();

        return response()->json([
            'search_title' => $search->title,
            'exported_at' => now()->toDateTimeString(),
            'data' => $answers
        ]);
    }
}