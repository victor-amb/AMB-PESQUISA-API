<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Search;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * LISTAR USUÁRIOS ADMINISTRATIVOS (Master vê tudo | Diretor vê co-gestores do seu escopo)
     */
    public function index(Request $request): JsonResponse
    {
        $currentUser = $request->user();

        // Carrega as relações administrativas corretas da nova model User
        $query = User::query()->with([
            'specialties',
            'authoredSearches:id,title',
            'managedSearches:id,title'
        ]);

        // 1. 🛡️ CAMADA DE SEGURANÇA: Restrição de Escopo para o Usuário Diretor
        if ($currentUser->type === 'director') {
            $directorSpecialties = $currentUser->specialties->pluck('id')->toArray();

            // Identifica as pesquisas que o diretor logado é autor ou co-gestor
            $managedSearchIds = Search::where('author_id', $currentUser->id)
                ->orWhereHas('managers', function ($q) use ($currentUser) {
                    $q->where('users.id', $currentUser->id);
                })->pluck('id')->toArray();

            $query->where(function ($q) use ($currentUser, $directorSpecialties, $managedSearchIds) {
                // O Diretor vê a si mesmo
                $q->where('id', $currentUser->id)
                    // OU vê outros Diretores que compartilham da mesma especialidade médica
                    ->orWhere(function ($subQuery) use ($directorSpecialties) {
                        $subQuery->where('type', 'director')
                            ->whereHas('specialties', function ($sub) use ($directorSpecialties) {
                                $sub->whereIn('specialties.id', $directorSpecialties);
                            });
                    })
                    // OU vê diretores co-gestores que dividem a gestão de algum projeto científico com ele
                    ->orWhereHas('managedSearches', function ($sub) use ($managedSearchIds) {
                        $sub->whereIn('searches.id', $managedSearchIds);
                    });
            });
        }

        // 2. 🎛️ CAMADA DE FILTROS AVANÇADOS

        // Filtro por Nome ou E-mail (Barra de Busca)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filtro por Nível de Acesso (master ou director)
        if ($request->filled('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        // Filtro por Status Operacional (Ativo/Inativo)
        if ($request->has('active') && $request->active !== 'all') {
            $isActive = filter_var($request->active, FILTER_VALIDATE_BOOLEAN);
            $query->where('active', $isActive);
        }

        // Filtro por Especialidades vinculadas ao operador administrativo
        if ($request->filled('specialties')) {
            $specialties = is_array($request->specialties)
                ? $request->specialties
                : explode(',', $request->specialties);

            $query->whereHas('specialties', function ($q) use ($specialties) {
                $q->whereIn('specialties.id', $specialties);
            });
        }

        // Filtro de Engajamento em Pesquisa Base
        if ($request->filled('search_base')) {
            $searchId = $request->search_base;
            $engagements = $request->input('engagement', []);

            if (is_string($engagements)) {
                $engagements = array_filter(explode(',', $engagements));
            }

            if (!empty($engagements)) {
                $query->where(function ($q) use ($searchId, $engagements) {

                    if (in_array('invited', $engagements)) {
                        // Convidados: estão na tabela search_invitations
                        $q->orWhereIn('users.email', function ($sub) use ($searchId) {
                            $sub->select('email')
                                ->from('search_invitations')
                                ->where('search_id', $searchId);
                        });
                    }

                    if (in_array('completed', $engagements)) {
                        // Respostas Completas: estão na tabela search_answers via responders com status 'completed'
                        $q->orWhereIn('users.email', function ($sub) use ($searchId) {
                            $sub->select('r.email')
                                ->from('responders as r')
                                ->join('search_answers as sa', 'sa.responder_id', '=', 'r.id')
                                ->where('sa.search_id', $searchId)
                                ->where('sa.progress_status', 'completed');
                        });
                    }

                    if (in_array('partial', $engagements)) {
                        // Respostas Parciais: estão na tabela search_answers com status 'in_progress'
                        $q->orWhereIn('users.email', function ($sub) use ($searchId) {
                            $sub->select('r.email')
                                ->from('responders as r')
                                ->join('search_answers as sa', 'sa.responder_id', '=', 'r.id')
                                ->where('sa.search_id', $searchId)
                                ->where('sa.progress_status', 'in_progress');
                        });
                    }

                    if (in_array('unanswered', $engagements)) {
                        // Não responderam: Foram convidados (search_invitations) mas não possuem registro em search_answers
                        $q->orWhereIn('users.email', function ($sub) use ($searchId) {
                            $sub->select('si.email')
                                ->from('search_invitations as si')
                                ->where('si.search_id', $searchId)
                                ->whereNotIn('si.email', function ($sub2) use ($searchId) {
                                    $sub2->select('r2.email')
                                        ->from('responders as r2')
                                        ->join('search_answers as sa2', 'sa2.responder_id', '=', 'r2.id')
                                        ->where('sa2.search_id', $searchId);
                                });
                        });
                    }

                });
            }
        }

        return response()->json($query->latest()->paginate(15));
    }

    /**
     * CRIAR NOVO OPERADOR ADMINISTRATIVO (Diretor ou Master)
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $currentUser = $request->user();
        $data = $request->validated();

        // 🔒 Trava Protetora: Diretor nunca pode criar um usuário Master
        if ($currentUser->type === 'director' && isset($data['type']) && $data['type'] === 'master') {
            return response()->json([
                'message' => 'Operação proibida. Diretores não possuem permissão para criar usuários do tipo Master.'
            ], 403);
        }

        // Se for Diretor criando outro Diretor, a pesquisa associada vira obrigatória
        if ($currentUser->type === 'director') {
            if (!isset($data['search_id'])) {
                return response()->json(['message' => 'O campo search_id é obrigatório para cadastro efetuado por Diretores.'], 422);
            }

            $search = Search::findOrFail($data['search_id']);
            $hasPower = $search->author_id === $currentUser->id ||
                $search->managers()->where('user_id', $currentUser->id)->exists();

            if (!$hasPower) {
                return response()->json([
                    'message' => 'Você não possui autorização sobre a pesquisa informada para vincular co-gestores.'
                ], 403);
            }
        }

        // Registra o ID de quem está convidando
        $data['inviter_id'] = $currentUser->id;
        $data['password'] = Hash::make($data['password']);

        $newUser = User::create($data);

        // Vincula especialidades administrativas se enviadas
        if (!empty($data['specialties'])) {
            $newUser->specialties()->sync($data['specialties']);
        }

        // Se foi criado por um Diretor, insere automaticamente o novo Diretor na co-gestão da pesquisa informada
        if ($currentUser->type === 'director' && isset($search)) {
            $search->managers()->attach($newUser->id);
        }

        return response()->json([
            'message' => 'Usuário administrativo cadastrado com sucesso!',
            'user' => $newUser->load('specialties')
        ], 201);
    }

    /**
     * VISUALIZAR DETALHES DE UM USUÁRIO ADMINISTRATIVO
     */
    public function show(Request $request, $id): JsonResponse
    {
        $currentUser = $request->user();
        $user = User::with('specialties')->findOrFail($id);

        // Se for Diretor, valida se o alvo compartilha do mesmo ecossistema
        if ($currentUser->type === 'director' && $user->id !== $currentUser->id) {
            $directorSpecialties = $currentUser->specialties->pluck('id')->toArray();
            $managedSearchIds = Search::where('author_id', $currentUser->id)
                ->orWhereHas('managers', function ($q) use ($currentUser) {
                    $q->where('users.id', $currentUser->id);
                })->pluck('id')->toArray();

            $inSpecialty = $user->specialties()->whereIn('specialties.id', $directorSpecialties)->exists();
            $inManagers = $user->managedSearches()->whereIn('searches.id', $managedSearchIds)->exists();

            if (!$inSpecialty && !$inManagers) {
                return response()->json(['message' => 'Acesso negado a este perfil corporativo.'], 403);
            }
        }

        return response()->json($user);
    }

    /**
     * ATUALIZAR USUÁRIO ADMINISTRATIVO
     */
    public function update(UpdateUserRequest $request, $id): JsonResponse
    {
        $currentUser = $request->user();
        $user = User::findOrFail($id);
        $data = $request->validated();

        // ⚠️ 1. Impede que um Master edite dados de OUTRO Master
        if ($user->type === 'master' && $currentUser->id !== $user->id) {
            return response()->json([
                'message' => 'Acesso negado. Um Administrador Master não possui permissão para alterar o perfil de outro Master.'
            ], 403);
        }

        // 🛡️ 2. NENHUM usuário pode alterar seu próprio 'type' e 'active' (inclusive o Master)
        if ($currentUser->id === $user->id) {
            unset($data['type'], $data['active']);
        }

        // 3. Validações de escopo do Diretor
        if ($currentUser->type === 'director') {
            if (isset($data['type']) && $data['type'] === 'master') {
                return response()->json(['message' => 'Você não pode promover usuários ao cargo de Master.'], 403);
            }

            if ($user->id !== $currentUser->id) {
                $directorSpecialties = $currentUser->specialties->pluck('id')->toArray();
                $managedSearchIds = Search::where('author_id', $currentUser->id)
                    ->orWhereHas('managers', function ($q) use ($currentUser) {
                        $q->where('users.id', $currentUser->id);
                    })->pluck('id')->toArray();

                $inSpecialty = $user->specialties()->whereIn('specialties.id', $directorSpecialties)->exists();
                $inManagers = $user->managedSearches()->whereIn('searches.id', $managedSearchIds)->exists();

                if (!$inSpecialty && !$inManagers) {
                    return response()->json(['message' => 'Você não tem permissão para editar este usuário.'], 403);
                }
            }
        }

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        if (isset($data['specialties'])) {
            $user->specialties()->sync($data['specialties']);
        }

        return response()->json([
            'message' => 'Usuário administrativo atualizado com sucesso!',
            'user' => $user->load('specialties')
        ]);
    }

    /**
     * EXCLUIR USUÁRIO (Soft Delete - Exclusivo Master)
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        if ($request->user()->type !== 'master') {
            return response()->json(['message' => 'Acesso negado. Apenas administradores Master podem remover usuários.'], 403);
        }

        $user = User::findOrFail($id);

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Prevenção de auto-exclusão ativada. Você não pode deletar sua própria conta raiz.'], 400);
        }

        $user->delete();

        return response()->json(['message' => 'Usuário excluído do ecossistema administrativo com sucesso.']);
    }

    /**
     * GESTÃO DE DADOS PRÓPRIOS (Perfil Autenticado)
     */
    public function updateProfile(UpdateUserRequest $request): JsonResponse
    {
        // O corpo do método continua EXATAMENTE igual
        $user = $request->user();
        $data = $request->validated();

        // 🔒 BLINDAGEM DE ESCOPO: Ignora tentativas de auto-privilégio
        unset($data['type'], $data['active'], $data['password'], $data['current_password']);


        // 🌟 NOVA REGRA: Marca os dados como confirmados caso seja um Respondente
        if ($user instanceof \App\Models\Responder) {
            $data['confirmed_data'] = true;
        }

        $user->update($data);

        // 🌟 UX & ONBOARDING: Aceita automaticamente o convite de sistema para não poluir as notificações
        if ($user instanceof \App\Models\Responder) {
            \App\Models\SystemInvitation::where('email', $user->email)
                ->where('status', 'pending')
                ->update(['status' => 'registered']);
        }

        return response()->json([
            'message' => 'Seu perfil foi atualizado com sucesso!',
            'user' => $user->load('specialties')
        ]);
    }
}