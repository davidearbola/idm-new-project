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
        Schema::create('disponibilita_medici', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medico_id')->constrained('users')->onDelete('cascade');
            $table->tinyInteger('giorno_settimana')->comment('1=Lunedì, 2=Martedì, ..., 7=Domenica');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('intervallo_slot')->default(30)->comment('Durata slot in minuti');
            $table->integer('poltrone_disponibili')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indice per query veloci
            $table->index(['medico_id', 'giorno_settimana', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('disponibilita_medici');
    }
};
