<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appuntamento extends Model
{
    use HasFactory;

    protected $table = 'appuntamenti';

    protected $fillable = [
        'proposta_id',
        'poltrona_id',
        'starting_date_time',
        'ending_date_time',
        'stato',
        'note',
    ];

    protected $casts = [
        'starting_date_time' => 'datetime',
        'ending_date_time' => 'datetime',
    ];

    public function proposta(): BelongsTo
    {
        return $this->belongsTo(ContropropostaMedico::class, 'proposta_id');
    }

    public function poltrona(): BelongsTo
    {
        return $this->belongsTo(PoltronaMedico::class, 'poltrona_id');
    }
}
