<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Specialty;
use App\Http\Requests\StoreSpecialtyRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SpecialtyController extends Controller
{
    /**
     * Listar Especialidades Médicas
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Se for Master, traz histórico completo (ativos e inativos) para o CRUD dedicado
        if ($user && $user->type === 'master') {
            $specialties = Specialty::orderBy('name')->paginate(15);
            return response()->json($specialties);
        }

        // Se for Diretor ou Respondente, expõe unicamente as especialidades ativas
        $activeSpecialties = Specialty::where('active', true)->orderBy('name')->get();
        return response()->json($activeSpecialties);
    }

    /**
     * Criar Especialidade Médica (Suporta Binary Multipart)
     */
    public function store(StoreSpecialtyRequest $request): JsonResponse
    {
        if ($request->user()->type !== 'master') {
            return response()->json(['message' => 'Operação exclusiva para administradores Master.'], 403);
        }

        $data = $request->validated();
        $data['active'] = true; // Nasce ativa por padrão

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('specialties', 'public');
            $data['logo_url'] = Storage::url($path);
        }

        $specialty = Specialty::create($data);

        return response()->json([
            'message' => 'Especialidade médica registrada com sucesso.',
            'data' => $specialty
        ], 201);
    }

    /**
     * Visualizar Detalhes
     */
    public function show($id): JsonResponse
    {
        $specialty = Specialty::findOrFail($id);
        return response()->json($specialty);
    }

    /**
     * Atualizar Especialidade (Suporta mutações parciais ou troca de logo)
     */
    public function update(Request $request, $id): JsonResponse
    {
        if ($request->user()->type !== 'master') {
            return response()->json(['message' => 'Operação exclusiva para administradores Master.'], 403);
        }

        $specialty = Specialty::findOrFail($id);
        
        // Validação inline pragmática por conta do suporte multipart via POST/PUT override do PHP
        $request->validate([
            'name' => 'required|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,svg|max:2048'
        ]);

        $specialty->name = $request->input('name');

        if ($request->hasFile('logo')) {
            // Remove mídia legada se aplicável
            if ($specialty->logo_url) {
                $oldPath = str_replace('/storage/', '', $specialty->logo_url);
                Storage::disk('public')->delete($oldPath);
            }

            $path = $request->file('logo')->store('specialties', 'public');
            $specialty->logo_url = Storage::url($path);
        }

        $specialty->save();

        return response()->json([
            'message' => 'Catálogo atualizado com sucesso.',
            'data' => $specialty
        ]);
    }

    /**
     * Exclusão Lógica / Inativação (Regra de Ouro)
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        if ($request->user()->type !== 'master') {
            return response()->json(['message' => 'Permissão negada.'], 403);
        }

        $specialty = Specialty::findOrFail($id);
        
        // Alterna dinamicamente o status para preservar o histórico relacional das tabelas
        $specialty->active = !$specialty->active;
        $specialty->save();

        $statusText = $specialty->active ? 'reativada' : 'inativada';

        return response()->json([
            'message' => "Especialidade médica {$statusText} com sucesso no catálogo.",
            'data' => $specialty
        ]);
    }
}