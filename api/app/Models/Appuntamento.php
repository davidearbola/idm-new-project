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
        'slot_appuntamento_id',
        'medico_id',
        'proposta_id',
        'stato',
        'note',
    ];

    public function slotAppuntamento(): BelongsTo
    {
        return $this->belongsTo(SlotAppuntamento::class);
    }

    public function medico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'medico_id');
    }

    public function proposta(): BelongsTo
    {
        return $this->belongsTo(ContropropostaMedico::class, 'proposta_id');
    }
}
