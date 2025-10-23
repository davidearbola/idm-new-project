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
        Schema::create('slot_appuntamenti', function (Blueprint $table) {
            $table->id();
            $table->foreignId('disponibilita_medico_id')->constrained('disponibilita_medici')->onDelete('cascade');
            $table->foreignId('medico_id')->constrained('users')->onDelete('cascade');
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->integer('totale_poltrone');
            $table->integer('poltrone_prenotate')->default(0);
            $table->timestamps();

            // Indici per query veloci
            $table->index(['medico_id', 'start_time', 'end_time']);
            $table->index('disponibilita_medico_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slot_appuntamenti');
    }
};
