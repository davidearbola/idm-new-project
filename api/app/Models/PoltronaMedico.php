<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PoltronaMedico extends Model
{
    use HasFactory;

    protected $table = 'poltrone_medici';

    protected $fillable = [
        'medico_id',
        'nome_poltrona',
    ];

    public function medico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'medico_id');
    }

    public function disponibilita(): HasMany
    {
        return $this->hasMany(DisponibilitaMedico::class, 'poltrona_id');
    }

    public function appuntamenti(): HasMany
    {
        return $this->hasMany(Appuntamento::class, 'poltrona_id');
    }
}
