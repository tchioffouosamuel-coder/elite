<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preinscriptions', function (Blueprint $table) {
            $table->foreignId('bus_versement_id')->nullable()->after('versement_id')->constrained('bus_versements')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('preinscriptions', function (Blueprint $table) {
            $table->dropForeign(['bus_versement_id']);
            $table->dropColumn('bus_versement_id');
        });
    }
};