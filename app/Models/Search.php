<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Search extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'objective',
        'questions',
        'consent_term',
        'instructions',
        'status',
        'start_date',
        'end_date',
        'author_id',
        'parent_search_id',
    ];

    protected $casts = [
        'questions' => 'array', // Estrutura dinâmica tratada como array nativo
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    /**
     * Criador original da pesquisa (Master ou Diretor).
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Especialidades médicas às quais este questionário é segmentado.
     */
    public function specialties(): BelongsToMany
    {
        return $this->belongsToMany(Specialty::class, 'search_specialties');
    }

    /**
     * Co-gestores convidados que auxiliam na administração desta pesquisa.
     */
    public function managers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'search_managers');
    }

    /**
     * Todos os convites disparados para esta pesquisa específica.
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(SearchInvitation::class, 'search_id');
    }

    /**
     * Respostas coletadas (parciais ou concluídas) para esta pesquisa.
     */
    public function answers(): HasMany
    {
        return $this->hasMany(SearchAnswer::class, 'search_id');
    }

    /**
     * Pesquisa precedente (Etapa anterior que deu origem a esta).
     */
    public function parentSearch(): BelongsTo
    {
        return $this->belongsTo(Search::class, 'parent_search_id');
    }

    /**
     * Pesquisas desdobradas a partir desta (Próximas etapas dependentes).
     */
    public function childSearches(): HasMany
    {
        return $this->hasMany(Search::class, 'parent_search_id');
    }
}