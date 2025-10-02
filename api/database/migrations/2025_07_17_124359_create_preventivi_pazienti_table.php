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
            $table->string('email_paziente')->nullable();
            $table->string('cellulare_paziente')->nullable();
            $table->string('nome_paziente')->nullable();
            $table->string('cognome_paziente')->nullable();
            $table->string('indirizzo_paziente')->nullable();
            $table->string('citta_paziente')->nullable();
            $table->string('provncia_paziente')->nullable();
            $table->string('cap_paziente')->nullable();
            $table->string('lat_paziente')->nullable();
            $table->string('lng_paziente')->nullable();
            $table->string('file_path');
            $table->string('file_name_originale');
            $table->enum('stato_elaborazione', ['analisi_voci_in_corso', 'attesa_conferma_paziente', 'ricerca_proposte', 'proposte_pronte', 'senza_proposte', 'errore'])->default('analisi_voci_in_corso');
            $table->json('json_preventivo')->nullable();
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
