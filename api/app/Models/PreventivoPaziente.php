<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreventivoPaziente extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'preventivi_pazienti';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'file_path',
        'file_name_originale',
        'stato_elaborazione',
        'json_preventivo',
        'messaggio_errore',
        'email_paziente',
        'cellulare_paziente',
        'nome_paziente',
        'cognome_paziente',
        'indirizzo_paziente',
        'citta_paziente',
        'provincia_paziente',
        'cap_paziente',
        'lat_paziente',
        'lng_paziente',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'json_preventivo' => 'array',
        'lat_paziente' => 'decimal:7',
        'lng_paziente' => 'decimal:7',
    ];

    public function controproposte()
    {
        return $this->hasMany(ContropropostaMedico::class);
    }
}
