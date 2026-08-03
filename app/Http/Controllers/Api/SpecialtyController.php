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
     * Trava de Segurança: Verifica se o usuário autenticado é um Master
     */
    private function isMaster($user): bool
    {
        return $user && ($user instanceof \App\Models\User) && $user->type === 'master';
    }

    /**
     * Listar Especialidades Médicas
     */
    public function index(Request $request): JsonResponse
    {
        if (!$this->isMaster($request->user())) {
            return response()->json(['message' => 'Operação exclusiva para administradores Master.'], 403);
        }

        $specialties = Specialty::orderBy('name')->paginate(15);
        return response()->json($specialties);
    }

    /**
     * Criar Especialidade Médica
     */
    public function store(StoreSpecialtyRequest $request): JsonResponse
    {
        if (!$this->isMaster($request->user())) {
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
    public function show(Request $request, $id): JsonResponse
    {
        if (!$this->isMaster($request->user())) {
            return response()->json(['message' => 'Operação exclusiva para administradores Master.'], 403);
        }

        $specialty = Specialty::findOrFail($id);
        return response()->json($specialty);
    }

    /**
     * Atualizar Especialidade
     */
    public function update(Request $request, $id): JsonResponse
    {
        if (!$this->isMaster($request->user())) {
            return response()->json(['message' => 'Operação exclusiva para administradores Master.'], 403);
        }

        $specialty = Specialty::findOrFail($id);
        
        $request->validate([
            'name' => 'required|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,svg|max:2048'
        ]);

        $specialty->name = $request->input('name');

        if ($request->hasFile('logo')) {
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
     * Exclusão Lógica / Inativação
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        if (!$this->isMaster($request->user())) {
            return response()->json(['message' => 'Operação exclusiva para administradores Master.'], 403);
        }

        $specialty = Specialty::findOrFail($id);
        
        $specialty->active = !$specialty->active;
        $specialty->save();

        $statusText = $specialty->active ? 'reativada' : 'inativada';

        return response()->json([
            'message' => "Especialidade médica {$statusText} com sucesso no catálogo.",
            'data' => $specialty
        ]);
    }
}