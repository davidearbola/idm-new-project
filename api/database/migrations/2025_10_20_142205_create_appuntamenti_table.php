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
            $table->foreignId('slot_appuntamento_id')->constrained('slot_appuntamenti')->onDelete('cascade');
            $table->foreignId('medico_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('proposta_id')->constrained('controproposte_medici')->onDelete('cascade');
            $table->enum('stato', ['confermato', 'completato', 'cancellato'])->default('confermato');
            $table->text('note')->nullable();
            $table->timestamps();

            // Indici per query veloci
            $table->index(['medico_id', 'stato']);
            $table->index('proposta_id');
            $table->index('slot_appuntamento_id');
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
