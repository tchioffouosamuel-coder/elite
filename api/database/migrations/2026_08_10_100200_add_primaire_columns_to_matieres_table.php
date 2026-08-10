<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Au primaire, une matière n'est pas notée sur 20 avec un coefficient :
     * elle est « notée sur » un barème propre (`notation`, 10 à 100 dans
     * archange, colonne `disciplines.cot`), et la moyenne générale ramène le
     * total obtenu sur 20 — le barème joue donc le rôle du coefficient.
     *
     * Chaque matière est évaluée sur trois volets (oral, écrit, savoir-être),
     * plus un quatrième volet « pratique » pour les matières qui s'y prêtent.
     */
    public function up(): void
    {
        Schema::table('matieres', function (Blueprint $table) {
            $table->string('nom_en')->nullable()->after('nom');
            $table->unsignedSmallInteger('notation')->nullable()->after('abbreviation');
            $table->boolean('evalue_pratique')->default(false)->after('notation');
        });
    }

    public function down(): void
    {
        Schema::table('matieres', function (Blueprint $table) {
            $table->dropColumn(['nom_en', 'notation', 'evalue_pratique']);
        });
    }
};
