<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Responder extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'crm',
        'crm_state',
        'metadata',
        'active',
        'confirmed_data',
        'inviter_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'metadata' => 'array', // Converte automaticamente o JSON em array do PHP
        'active' => 'boolean',
        'confirmed_data' => 'boolean',
        'password' => 'hashed',
    ];

    /**
     * Especialidades médicas que este respondente possui (caso seja médico).
     */
    public function specialties(): BelongsToMany
    {
        return $this->belongsToMany(Specialty::class, 'responder_specialties');
    }

    /**
     * Respostas iniciadas ou concluídas por este participante.
     */
    public function answers(): HasMany
    {
        return $this->hasMany(SearchAnswer::class, 'responder_id');
    }

    /**
     * Histórico de convites específicos recebidos para responder pesquisas.
     */
    public function searchInvitations(): HasMany
    {
        return $this->hasMany(SearchInvitation::class, 'responder_id');
    }

    /**
     * Operador administrativo que realizou o cadastro inicial deste respondente.
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }
}