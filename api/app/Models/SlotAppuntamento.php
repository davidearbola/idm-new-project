<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SlotAppuntamento extends Model
{
    use HasFactory;

    protected $table = 'slot_appuntamenti';

    protected $fillable = [
        'disponibilita_medico_id',
        'medico_id',
        'start_time',
        'end_time',
        'totale_poltrone',
        'poltrone_prenotate',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'totale_poltrone' => 'integer',
        'poltrone_prenotate' => 'integer',
    ];

    public function disponibilitaMedico(): BelongsTo
    {
        return $this->belongsTo(DisponibilitaMedico::class);
    }

    public function medico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'medico_id');
    }

    public function appuntamenti(): HasMany
    {
        return $this->hasMany(Appuntamento::class);
    }

    // Helper per verificare se lo slot è disponibile
    public function isDisponibile(): bool
    {
        return $this->poltrone_prenotate < $this->totale_poltrone;
    }

    // Helper per ottenere poltrone disponibili
    public function poltronLibere(): int
    {
        return $this->totale_poltrone - $this->poltrone_prenotate;
    }
}
