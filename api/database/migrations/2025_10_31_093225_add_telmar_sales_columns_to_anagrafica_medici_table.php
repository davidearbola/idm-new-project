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
        Schema::table('anagrafica_medici', function (Blueprint $table) {
            $table->string('pin_telmar_sales')->nullable();
            $table->integer('id_sales_cms', false, true)->length(11)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('anagrafica_medici', function (Blueprint $table) {
            $table->dropColumn(['pin_telmar_sales', 'id_sales_cms']);
        });
    }
};
