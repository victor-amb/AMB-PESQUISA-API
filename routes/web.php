<?php

use Illuminate\Support\Facades\Route;

//Vai mandar para plataforma de respostas (AMB-PESQUISAS-USUARIOS) para completar o cadastro
Route::get('/onboarding/completar-perfil', function (Request $request) {
    if (! $request->hasValidSignature()) {
        abort(403, 'Este link de convite expirou ou é inválido.');
    }
    
    // Redireciona o usuário para a aplicação web de Onboarding passando o ID e a assinatura válida
    return redirect('https://amb-onboarding.org.br/cadastro?' . http_build_query($request->all()));
})->name('platform.onboarding');