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
}
