<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fiche de préparation enrichie d'une leçon : au-delà du simple titre,
     * l'enseignant y consigne ce qu'il compte y faire.
     */
    public function up(): void
    {
        Schema::table('progression_items', function (Blueprint $table) {
            $table->text('objectifs')->nullable()->after('description');
            $table->text('materiel')->nullable()->after('objectifs');
            $table->text('activites')->nullable()->after('materiel');
            $table->text('devoirs')->nullable()->after('activites');
        });
    }

    public function down(): void
    {
        Schema::table('progression_items', function (Blueprint $table) {
            $table->dropColumn(['objectifs', 'materiel', 'activites', 'devoirs']);
        });
    }
};
