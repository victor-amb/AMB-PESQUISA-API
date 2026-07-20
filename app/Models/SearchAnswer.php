<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'search_id',
        'responder_id',
        'progress_status',
        'answers',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'answers' => 'array', // Permite manipular as respostas dinâmicas direto pelo PHP
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Pesquisa correspondente a esta resposta.
     */
    public function search(): BelongsTo
    {
        return $this->belongsTo(Search::class, 'search_id');
    }

    /**
     * Participante que enviou ou está preenchendo as respostas.
     */
    public function responder(): BelongsTo
    {
        return $this->belongsTo(Responder::class, 'responder_id');
    }
}