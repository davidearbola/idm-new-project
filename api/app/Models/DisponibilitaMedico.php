<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisponibilitaMedico extends Model
{
    use HasFactory;

    protected $table = 'disponibilita_medici';

    protected $fillable = [
        'poltrona_id',
        'intervallo_minuti',
        'giorno_settimana',
        'starting_time',
        'ending_time',
    ];

    protected $casts = [
        'giorno_settimana' => 'integer',
        'intervallo_minuti' => 'integer',
    ];

    public function poltrona(): BelongsTo
    {
        return $this->belongsTo(PoltronaMedico::class, 'poltrona_id');
    }

    /**
     * Verifica se ci sono appuntamenti futuri per questa disponibilità
     */
    public function hasAppuntamentiFuturi(): bool
    {
        // Calcola tutte le date future per questo giorno della settimana
        $oggi = now();
        $giorniDaAggiungere = ($this->giorno_settimana - $oggi->dayOfWeek + 7) % 7;
        $prossimaOccorrenza = $oggi->copy()->addDays($giorniDaAggiungere)->setTimeFromTimeString($this->starting_time);

        // Se la prossima occorrenza è nel passato, passa alla settimana successiva
        if ($prossimaOccorrenza < $oggi) {
            $prossimaOccorrenza->addWeek();
        }

        // Verifica appuntamenti nella fascia oraria di questa disponibilità per tutte le future occorrenze
        return Appuntamento::where('poltrona_id', $this->poltrona_id)
            ->where('starting_date_time', '>=', $prossimaOccorrenza)
            ->whereNotIn('stato', ['cancellato', 'assente'])
            ->where(function ($query) use ($prossimaOccorrenza) {
                // Filtra solo appuntamenti che cadono nel giorno della settimana corretto
                $query->whereRaw('DAYOFWEEK(starting_date_time) = ?', [$this->giorno_settimana]);
            })
            ->where(function ($query) {
                // Filtra solo appuntamenti che rientrano nella fascia oraria
                $query->whereRaw('TIME(starting_date_time) >= ?', [$this->starting_time])
                      ->whereRaw('TIME(starting_date_time) < ?', [$this->ending_time]);
            })
            ->exists();
    }
}
