<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DisponibilitaMedico extends Model
{
    use HasFactory;

    protected $table = 'disponibilita_medici';

    protected $fillable = [
        'medico_id',
        'giorno_settimana',
        'start_time',
        'end_time',
        'intervallo_slot',
        'poltrone_disponibili',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'giorno_settimana' => 'integer',
        'intervallo_slot' => 'integer',
        'poltrone_disponibili' => 'integer',
    ];

    public function medico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'medico_id');
    }

    public function slotAppuntamenti(): HasMany
    {
        return $this->hasMany(SlotAppuntamento::class);
    }
}
