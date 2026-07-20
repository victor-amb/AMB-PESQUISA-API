<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Search;
use App\Models\Specialty;
use App\Models\Responder;
use App\Models\SearchAnswer;
use App\Models\SearchInvitation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Obter estatísticas consolidadas para a Home do Painel Administrativo.
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        // Garante que respondentes do app móvel não consumam dados gerenciais do painel
        if ($user instanceof User) {
            if ($user->type === 'master') {
                return $this->getMasterStats();
            }

            if ($user->type === 'director') {
                return $this->getDirectorStats($user);
            }
        }

        return response()->json(['message' => 'Perfil não autorizado para visualizar este painel.'], 403);
    }

    /**
     * MÉTRICAS DO MASTER: Visão Global do Sistema
     */
    private function getMasterStats(): JsonResponse
    {
        $totalSearches = Search::count();
        $activeSearches = Search::where('status', 'published')->count();
        
        $usersBreakdown = [
            'masters' => User::where('type', 'master')->count(),
            'directors' => User::where('type', 'director')->count(),
            'responders' => Responder::where('active', 1)->count()
        ];

        return response()->json([
            'total_searches' => $totalSearches,
            'active_searches' => $activeSearches,
            'users_breakdown' => $usersBreakdown,
            'target_doctors' => array_sum($usersBreakdown), // Soma de todos os usuários do ecossistema
            'specialties_count' => Specialty::where('active', 1)->count(),
            'recent_searches' => Search::with('author:id,name')->latest()->take(5)->get()
        ]);
    }

    /**
     * MÉTRICAS DO DIRETOR: Visão de Engajamento das Suas Pesquisas
     */
    private function getDirectorStats(User $user): JsonResponse
    {
        // 1. Identifica o escopo de IDs de pesquisas gerenciadas por este Diretor (Autor ou Co-gestor)
        $managedSearchIds = Search::where('author_id', $user->id)
            ->orWhereHas('managers', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            })->pluck('id')->toArray();

        $totalSearches = count($managedSearchIds);

        // 2. Total de respostas finalizadas estritamente ligadas às pesquisas dele
        $totalAnswers = SearchAnswer::whereIn('search_id', $managedSearchIds)
            ->where('progress_status', 'completed')
            ->count();

        // 🌟 KPI: Usuários Atingidos (Médicos Únicos que concluíram ao menos uma pesquisa dele)
        $targetDoctors = SearchAnswer::whereIn('search_id', $managedSearchIds)
            ->where('progress_status', 'completed')
            ->distinct('responder_id')
            ->count('responder_id');

        // 🌟 KPI: Porcentagem de Adesão (Apenas em pesquisas publicadas)
        $publishedSearchIds = Search::whereIn('id', $managedSearchIds)
            ->where('status', 'published')
            ->pluck('id')->toArray();

        $activeSearchesCount = count($publishedSearchIds);

        $totalPublishedInvitations = SearchInvitation::whereIn('search_id', $publishedSearchIds)->count();
        $totalPublishedAnswers = SearchAnswer::whereIn('search_id', $publishedSearchIds)
            ->where('progress_status', 'completed')
            ->count();

        $responseRate = $totalPublishedInvitations > 0
            ? round(($totalPublishedAnswers / $totalPublishedInvitations) * 100, 1)
            : 0;

        // 4. Convites de co-gestão enviados por outros diretores aguardando o seu aceite push
        $pendingInvites = SearchInvitation::where('email', $user->email)
            ->where('status', 'sent')
            ->whereNull('responder_id') // Garante que é convite de co-gestão
            ->count();

        $recentSearches = Search::withExists('answers as has_answers')
            ->whereIn('id', $managedSearchIds)
            ->latest()
            ->take(3)
            ->get(['id', 'title', 'status']);

        return response()->json([
            'total_searches' => $totalSearches,
            'total_answers' => $totalAnswers,
            'response_rate' => $responseRate,
            'active_searches_count' => $activeSearchesCount,
            'target_doctors' => $targetDoctors,
            'specialties_count' => 0,
            'pending_invites' => $pendingInvites,
            'recent_searches' => $recentSearches
        ]);
    }
}