<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Specialty extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'image_path',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    /**
     * Diretores associados a esta especialidade.
     */
    public function directors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_specialties');
    }

    /**
     * Médicos participantes vinculados a esta especialidade.
     */
    public function responders(): BelongsToMany
    {
        return $this->belongsToMany(Responder::class, 'responder_specialties');
    }

    /**
     * Pesquisas restritas a esta especialidade médica.
     */
    public function searches(): BelongsToMany
    {
        return $this->belongsToMany(Search::class, 'search_specialties');
    }
}