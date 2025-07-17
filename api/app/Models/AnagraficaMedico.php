<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnagraficaMedico extends Model
{
    protected $table = 'anagrafica_medici';

    protected $fillable = [
        'ragione_sociale',
        'p_iva',
        'cellulare',
        'indirizzo',
        'citta',
        'cap',
        'provincia',
        'lat',
        'lng',
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
