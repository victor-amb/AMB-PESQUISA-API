<?php

use App\Http\Controllers\Api\InvitationController; // <-- Altere para o InvitationController
use App\Models\Search;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    $now = now();

    // 1. ABRIR PESQUISAS AGENDADAS
    $toPublish = Search::where('status', 'draft')
        ->whereNotNull('start_date')
        ->where('start_date', '<=', $now)
        ->get();

    foreach ($toPublish as $search) {
        $search->update(['status' => 'published']);
        
        // CORRIGIDO: Dispara chamando o InvitationController correto
        app(InvitationController::class)->broadcastSearchLaunch($search);
    }

    // 2. FECHAR PESQUISAS AGENDADAS
    Search::where('status', 'published')
        ->whereNotNull('end_date')
        ->where('end_date', '<=', $now)
        ->update(['status' => 'closed']);
        
})->everyMinute();