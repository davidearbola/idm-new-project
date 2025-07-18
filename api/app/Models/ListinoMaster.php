<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ListinoMaster extends Model
{
    use HasFactory;
    protected $table = 'listino_master';
    protected $fillable = ['nome', 'descrizione', 'is_active'];
}
