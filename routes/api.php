<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\SearchAnswerController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SpecialtyController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// Verificação de API
Route::get('/', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'AMB Pesquisas API',
        'timestamp' => now()->toIso8601String(),
    ]);
});


// ========================================================
// 1. ROTAS PÚBLICAS (Sem Autenticação)
// ========================================================

Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [\App\Http\Controllers\Api\PasswordController::class, 'forgotPassword']);
Route::post('/reset-password', [\App\Http\Controllers\Api\PasswordController::class, 'resetPassword']);


// ========================================================
// 2. ROTAS PROTEGIDAS (Exigem Token Bearer - Sanctum)
// ========================================================
Route::middleware('auth:sanctum')->group(function () {
    
    // Autenticação & Gestão de Dados Próprios
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/profile', [UserController::class, 'updateProfile']);
    Route::post('/change-password', [\App\Http\Controllers\Api\PasswordController::class, 'changePassword']);

    // Módulo de Usuários e Especialidades
    Route::apiResource('users', UserController::class);
    Route::apiResource('specialties', SpecialtyController::class);

    // Módulo de Pesquisas Dinâmicas
    Route::apiResource('searches', SearchController::class);
    Route::post('/answers', [SearchAnswerController::class, 'store']);
    Route::get('/my-searches/answered', [SearchAnswerController::class, 'answeredSearches']);
    Route::get('/my-searches/pending', [SearchAnswerController::class, 'pendingSearches']);

    // ========================================================
    // MÓDULO DE CONVITES E PÚBLICO-ALVO (Centralizado)
    // ========================================================
    
    // Gestão Global de Convites
    Route::get('/invitations', [InvitationController::class, 'index']);
    Route::post('/invitations', [InvitationController::class, 'store']);
    Route::post('/invitations/validate-csv', [InvitationController::class, 'validateCsv']);
    Route::post('/invitations/custom-batch', [InvitationController::class, 'storeCustomBatch']);
    Route::post('/invitations/import-system', [InvitationController::class, 'importSystemTargets']);
    Route::delete('/invitations/{id}', [InvitationController::class, 'destroy']);
    Route::post('/invitations/{id}/resend', [InvitationController::class, 'resend']);
    Route::put('/invitations/{id}/accept', [InvitationController::class, 'accept']);
    Route::put('/invitations/{id}/decline', [InvitationController::class, 'decline']);
    Route::post('/searches/{id}/mass-invite', [InvitationController::class, 'massInviteByFilter']);

    // Convites atrelados especificamente a uma Pesquisa (Público-Alvo)
    Route::post('/searches/{id}/import-targets', [InvitationController::class, 'importTargets']);
    Route::delete('/searches/{search_id}/targets/{user_id}', [InvitationController::class, 'removeTarget']);
    Route::get('/searches/{id}/targets', [InvitationController::class, 'getTargets']);
    Route::get('/searches/{id}/eligible-users', [InvitationController::class, 'getEligibleUsers']);
    Route::get('/searches/{id}/eligible-managers', [InvitationController::class, 'getEligibleManagers']);
    Route::post('/searches/{id}/invite-co-manager', [InvitationController::class, 'inviteCoManager']);
    Route::get('/searches/{id}/invited-managers', [InvitationController::class, 'getInvitedManagers']);
    Route::delete('/searches/{id}/co-manager/{invitation_id}', [InvitationController::class, 'removeCoManager']);
    Route::post('/searches/{id}/eligible-users/bind', [InvitationController::class, 'bindEligibleUsers']);
    Route::get('/searches/{id}/invitations/summary', [InvitationController::class, 'getInvitationSummary']);
    Route::get('/searches/{id}/stats', [SearchController::class, 'stats']);
    Route::get('/searches/{id}/export-answers', [SearchController::class, 'exportAnswers']);

    // ========================================================
    // 3. ROTAS ESPECÍFICAS PARA INFOS DO FRONTEND
    // ========================================================
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
});