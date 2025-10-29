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
        // STEP 1: Aggiungi temporaneamente i nuovi valori all'enum mantenendo 'accettata'
        DB::statement("ALTER TABLE controproposte_medici MODIFY COLUMN stato ENUM('inviata', 'visualizzata', 'accettata', 'richiesta_chiamata', 'fissato_appuntamento', 'appuntamento_annullato', 'rifiutata') NOT NULL DEFAULT 'inviata'");

        // STEP 2: Aggiorna i record esistenti che hanno 'accettata' con il nuovo valore 'richiesta_chiamata'
        DB::table('controproposte_medici')
            ->where('stato', 'accettata')
            ->update(['stato' => 'richiesta_chiamata']);

        // STEP 3: Rimuovi 'accettata' dall'enum ora che non ci sono più record con quel valore
        DB::statement("ALTER TABLE controproposte_medici MODIFY COLUMN stato ENUM('inviata', 'visualizzata', 'richiesta_chiamata', 'fissato_appuntamento', 'appuntamento_annullato', 'rifiutata') NOT NULL DEFAULT 'inviata'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Ripristina i valori 'richiesta_chiamata' in 'accettata'
        DB::table('controproposte_medici')
            ->where('stato', 'richiesta_chiamata')
            ->update(['stato' => 'accettata']);

        // Ripristina il campo enum originale
        DB::statement("ALTER TABLE controproposte_medici MODIFY COLUMN stato ENUM('inviata', 'visualizzata', 'accettata', 'rifiutata') NOT NULL DEFAULT 'inviata'");
    }
};
