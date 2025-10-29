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
        Schema::table('preventivi_pazienti', function (Blueprint $table) {
            $table->uuid('access_token')->nullable()->unique()->after('id');
            $table->index('access_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('preventivi_pazienti', function (Blueprint $table) {
            $table->dropIndex(['access_token']);
            $table->dropColumn('access_token');
        });
    }
};
