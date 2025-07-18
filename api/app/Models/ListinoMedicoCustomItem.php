<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListinoMedicoCustomItem extends Model
{
    use HasFactory;
    protected $fillable = ['medico_user_id', 'nome', 'descrizione', 'prezzo'];

    public function medico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'medico_user_id');
    }
}
