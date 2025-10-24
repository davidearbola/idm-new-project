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
            $table->foreignId('poltrona_id')->constrained('poltrone_medici')->onDelete('cascade');
            $table->integer('intervallo_minuti')->default(30)->comment('Intervallo in minuti (fisso a 30)');
            $table->tinyInteger('giorno_settimana')->comment('1=Lunedì, 2=Martedì, ..., 7=Domenica');
            $table->time('starting_time');
            $table->time('ending_time');
            $table->timestamps();

            // Indice per query veloci
            $table->index(['poltrona_id', 'giorno_settimana'], 'disp_poltrona_giorno_idx');
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
