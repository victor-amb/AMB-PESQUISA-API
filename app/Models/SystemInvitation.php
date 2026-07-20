<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemInvitation extends Model
{
    use HasFactory;

    protected $fillable = ['sender_id', 'name', 'email', 'phone', 'status', 'metadata'];
    protected $casts = ['metadata' => 'array'];

    public function sender(): BelongsTo {
        return $this->belongsTo(User::class, 'sender_id');
    }
}