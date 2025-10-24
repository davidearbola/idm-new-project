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
        Schema::create('poltrone_medici', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medico_id')->constrained('users')->onDelete('cascade');
            $table->string('nome_poltrona');
            $table->timestamps();

            // Indice per query veloci
            $table->index('medico_id');

            // Constraint univoco: un medico non può avere due poltrone con lo stesso nome
            $table->unique(['medico_id', 'nome_poltrona']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('poltrone_medici');
    }
};
