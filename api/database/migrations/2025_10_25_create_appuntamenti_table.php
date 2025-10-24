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
        Schema::create('appuntamenti', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposta_id')->constrained('controproposte_medici')->onDelete('cascade');
            $table->foreignId('poltrona_id')->constrained('poltrone_medici')->onDelete('cascade');
            $table->dateTime('starting_date_time');
            $table->dateTime('ending_date_time');
            $table->enum('stato', ['confermato', 'completato', 'assente', 'cancellato'])->default('confermato');
            $table->text('note')->nullable();
            $table->timestamps();

            // Indici per query veloci
            $table->index('proposta_id');
            $table->index('poltrona_id');
            $table->index(['starting_date_time', 'ending_date_time']);
            $table->index('stato');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appuntamenti');
    }
};
