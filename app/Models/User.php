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

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'type',
        'name',
        'email',
        'phone',
        'active',
        'password',
        'inviter_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'active' => 'boolean',
        'password' => 'hashed',
    ];

/**
     * Especialidades/Sociedades que este Diretor coordena.
     */
    public function specialties(): BelongsToMany
    {
        return $this->belongsToMany(Specialty::class, 'user_specialties');
    }

    /**
     * Pesquisas criadas por este usuário.
     */
    public function authoredSearches(): HasMany
    {
        return $this->hasMany(Search::class, 'author_id');
    }

    /**
     * Pesquisas que este Diretor co-gerencia.
     */
    public function managedSearches(): BelongsToMany
    {
        return $this->belongsToMany(Search::class, 'search_managers');
    }

    /**
     * Quem convidou este administrador/diretor para o sistema.
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }

    /**
     * Administradores/Diretores convidados por este usuário.
     */
    public function invitees(): HasMany
    {
        return $this->hasMany(User::class, 'inviter_id');
    }
}