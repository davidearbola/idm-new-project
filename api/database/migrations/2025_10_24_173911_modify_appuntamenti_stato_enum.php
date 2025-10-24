<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Prima aggiungiamo temporaneamente i nuovi valori all'enum esistente
        DB::statement("ALTER TABLE appuntamenti MODIFY COLUMN stato ENUM('confermato', 'completato', 'assente', 'cancellato', 'nuovo', 'visualizzato') NOT NULL DEFAULT 'confermato'");

        // Aggiorna i record esistenti: confermato -> nuovo, completato -> visualizzato
        DB::table('appuntamenti')
            ->where('stato', 'confermato')
            ->update(['stato' => 'nuovo']);

        DB::table('appuntamenti')
            ->where('stato', 'completato')
            ->update(['stato' => 'visualizzato']);

        // Ora rimuoviamo i vecchi valori dall'enum e impostiamo il nuovo default
        DB::statement("ALTER TABLE appuntamenti MODIFY COLUMN stato ENUM('nuovo', 'visualizzato', 'assente', 'cancellato') NOT NULL DEFAULT 'nuovo'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Ripristina i valori vecchi
        DB::table('appuntamenti')
            ->where('stato', 'nuovo')
            ->update(['stato' => 'confermato']);

        DB::table('appuntamenti')
            ->where('stato', 'visualizzato')
            ->update(['stato' => 'completato']);

        // Ripristina l'enum originale
        DB::statement("ALTER TABLE appuntamenti MODIFY COLUMN stato ENUM('confermato', 'completato', 'assente', 'cancellato') NOT NULL DEFAULT 'confermato'");
    }
};
