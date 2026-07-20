<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchInvitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'search_id',
        'sender_id',
        'email',
        'phone',
        'name',
        'status',           // 'pending_publish', 'sent', 'accepted', 'declined'
        'delivery_status',  // 'standby', 'success_email', 'success_whatsapp', 'failed'
        'responder_id',     // FK para a tabela de respondentes
    ];

    /**
     * Pesquisa específica à qual este participante está sendo convocado.
     */
    public function search(): BelongsTo
    {
        return $this->belongsTo(Search::class, 'search_id');
    }

    /**
     * Administrador ou Diretor (User) que disparou este convite de pesquisa.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Perfil do respondente final vinculado ao convite.
     */
    public function responder(): BelongsTo
    {
        return $this->belongsTo(Responder::class, 'responder_id');
    }
}