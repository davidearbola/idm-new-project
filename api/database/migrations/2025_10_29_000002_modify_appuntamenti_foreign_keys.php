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
        Schema::table('appuntamenti', function (Blueprint $table) {
            // Rimuovi i constraint esistenti con cascade
            $table->dropForeign(['proposta_id']);
            $table->dropForeign(['poltrona_id']);
        });

        // Modifica le colonne per renderle nullable
        Schema::table('appuntamenti', function (Blueprint $table) {
            $table->unsignedBigInteger('proposta_id')->nullable()->change();
            $table->unsignedBigInteger('poltrona_id')->nullable()->change();
        });

        // Ricrea i constraint senza cascade (nullOnDelete per mantenere i record)
        Schema::table('appuntamenti', function (Blueprint $table) {
            $table->foreign('proposta_id')
                ->references('id')
                ->on('controproposte_medici')
                ->nullOnDelete();

            $table->foreign('poltrona_id')
                ->references('id')
                ->on('poltrone_medici')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appuntamenti', function (Blueprint $table) {
            // Rimuovi i constraint senza cascade
            $table->dropForeign(['proposta_id']);
            $table->dropForeign(['poltrona_id']);
        });

        // Ripristina le colonne come NOT NULL (rimuove i record con NULL prima)
        Schema::table('appuntamenti', function (Blueprint $table) {
            $table->unsignedBigInteger('proposta_id')->nullable(false)->change();
            $table->unsignedBigInteger('poltrona_id')->nullable(false)->change();
        });

        // Ripristina i constraint originali con cascade
        Schema::table('appuntamenti', function (Blueprint $table) {
            $table->foreign('proposta_id')
                ->references('id')
                ->on('controproposte_medici')
                ->onDelete('cascade');

            $table->foreign('poltrona_id')
                ->references('id')
                ->on('poltrone_medici')
                ->onDelete('cascade');
        });
    }
};
