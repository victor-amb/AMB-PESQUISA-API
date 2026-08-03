<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSearchAnswerRequest;
use App\Models\Search;
use App\Models\SearchAnswer;
use App\Models\SearchInvitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchAnswerController extends Controller
{
    /**
     * SUBMETER RESPOSTAS DA PESQUISA (Suporta salvar rascunho 'in_progress' ou fechar como 'completed')
     */
    public function store(StoreSearchAnswerRequest $request): JsonResponse
    {
        $responder = $request->user();
        $data = $request->validated();

        $search = Search::findOrFail($data['search_id']);

        if ($search->status !== 'published') {
            return response()->json(['message' => 'Esta pesquisa não está aceitando respostas no momento.'], 422);
        }

        $existingAnswer = SearchAnswer::where('search_id', $search->id)
            ->where('responder_id', $responder->id)
            ->first();

        if ($existingAnswer && $existingAnswer->progress_status === 'completed') {
            return response()->json(['message' => 'Você já concluiu a sua participação nesta pesquisa científica.'], 409);
        }

        // 🌟 ENRIQUECIMENTO DE DADOS: Associa o ID da pergunta com sua Label atual e seu Valor
        $questions = is_string($search->questions) ? json_decode($search->questions, true) : ($search->questions ?? []);
        $idToLabelMap = collect($questions)->pluck('label', 'id')->toArray();

        $richAnswers = [];
        foreach ($data['answers'] as $questionId => $answerValue) {
            $label = $idToLabelMap[$questionId] ?? $questionId; // Fallback para o ID se a label sumir
            $richAnswers[$questionId] = [
                'label' => $label,
                'value' => $answerValue
            ];
        }

        $data['answers'] = $richAnswers;

        $progressStatus = $request->input('progress_status', 'in_progress');
        $completedAt = $progressStatus === 'completed' ? now() : null;

        if ($existingAnswer) {
            $existingAnswer->update([
                'answers' => $data['answers'],
                'progress_status' => $progressStatus,
                'completed_at' => $completedAt,
            ]);
            $answer = $existingAnswer;
        } else {
            $answer = SearchAnswer::create([
                'search_id' => $search->id,
                'responder_id' => $responder->id,
                'answers' => $data['answers'],
                'progress_status' => $progressStatus,
                'started_at' => now(),
                'completed_at' => $completedAt,
            ]);
        }

        if ($progressStatus === 'completed') {
            SearchInvitation::where('search_id', $search->id)
                ->where('email', $responder->email)
                ->update(['status' => 'accepted']);
        }

        $message = $progressStatus === 'completed'
            ? 'Sua participação foi registrada e finalizada com sucesso! Obrigado.'
            : 'Rascunho de respostas salvo com sucesso.';

        return response()->json([
            'message' => $message,
            'answer' => $answer
        ], 200);
    }

    /**
     * LISTAR PESQUISAS JÁ CONCLUÍDAS PELO RESPONDENTE
     */
    public function answeredSearches(Request $request): JsonResponse
    {
        $responder = $request->user();

        $searches = Search::whereHas('answers', function ($query) use ($responder) {
            $query->where('responder_id', $responder->id)
                ->where('progress_status', 'completed');
        })->with('specialties')->latest()->paginate(15);

        return response()->json($searches);
    }


    /**
     * LISTAR PESQUISAS PENDENTES (Traz pesquisas não iniciadas E rascunhos em andamento)
     * REGRA: O respondente SÓ vê a pesquisa se houver um convite formal para o e-mail dele.
     */
    public function pendingSearches(Request $request): JsonResponse
    {
        $responder = $request->user();

        $query = Search::where('status', 'published')
            ->whereHas('invitations', function ($invQuery) use ($responder) {
                // Nova regra fundamental: Só passa se tiver convite para ele
                $invQuery->where('email', $responder->email);
            })
            ->where(function ($q) use ($responder) {
                // Traz se ele ainda não respondeu NADA
                $q->whereDoesntHave('answers', function ($sub) use ($responder) {
                    $sub->where('responder_id', $responder->id);
                })
                    // OU se a resposta dele ainda é um rascunho (in_progress)
                    ->orWhereHas('answers', function ($sub) use ($responder) {
                    $sub->where('responder_id', $responder->id)
                        ->where('progress_status', 'in_progress');
                });
            });

        $paginatedSearches = $query->with('specialties')->latest()->paginate(15);

        $paginatedSearches->getCollection()->transform(function ($search) use ($responder) {
            $userAnswerRecord = SearchAnswer::where('search_id', $search->id)
                ->where('responder_id', $responder->id)
                ->first();

            $search->has_draft = $userAnswerRecord ? true : false;

            if ($userAnswerRecord && $userAnswerRecord->answers) {
                $flatAnswers = [];
                foreach ($userAnswerRecord->answers as $questionId => $answerData) {
                    $flatAnswers[$questionId] = (is_array($answerData) && isset($answerData['value']))
                        ? $answerData['value']
                        : $answerData;
                }
                $search->saved_answers = $flatAnswers;
            } else {
                $search->saved_answers = null;
            }

            return $search;
        });

        return response()->json($paginatedSearches);
    }
}