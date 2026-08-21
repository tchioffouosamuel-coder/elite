<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eleves', function (Blueprint $table) {
            $table->string('lieu_delivrance_acte')->nullable()->after('numero_acte_naissance');
            $table->string('officier_etat_civil')->nullable()->after('lieu_delivrance_acte');
        });
    }

    public function down(): void
    {
        Schema::table('eleves', function (Blueprint $table) {
            $table->dropColumn(['lieu_delivrance_acte', 'officier_etat_civil']);
        });
    }
};
