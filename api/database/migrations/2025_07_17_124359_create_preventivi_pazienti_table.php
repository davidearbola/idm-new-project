<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('preventivi_pazienti', function (Blueprint $table) {
            $table->id();
            $table->string('file_path');
            $table->string('file_name_originale');
            $table->enum('stato_elaborazione', ['caricato', 'in_elaborazione', 'completato', 'attesa_dati_paziente', 'ricerca_proposte', 'proposte_pronte', 'senza_proposte', 'errore'])->default('caricato');
            $table->json('json_preventivo')->nullable();
            $table->text('messaggio_errore')->nullable();
            $table->string('email_paziente')->nullable();
            $table->string('cellulare_paziente')->nullable();
            $table->string('nome_paziente')->nullable();
            $table->string('cognome_paziente')->nullable();
            $table->string('indirizzo_paziente')->nullable();
            $table->string('citta_paziente')->nullable();
            $table->string('provincia_paziente')->nullable();
            $table->string('cap_paziente')->nullable();
            $table->decimal('lat_paziente', 10, 7)->nullable();
            $table->decimal('lng_paziente', 10, 7)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('preventivi_pazienti');
    }
};
