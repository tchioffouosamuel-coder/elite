<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Champs statistiques manquants sur la préinscription : région/département
 * d'origine (pour les élèves camerounais, y compris déplacés internes,
 * Bororo, Baka), situation de handicap, et école précédente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eleves', function (Blueprint $table) {
            $table->string('region_origine')->nullable()->after('nationalite');
            $table->string('departement_origine')->nullable()->after('region_origine');
            $table->string('handicap')->nullable()->after('baka');
            $table->string('type_handicap')->nullable()->after('handicap');
            $table->string('ecole_precedente')->nullable()->after('redoublant');
        });
    }

    public function down(): void
    {
        Schema::table('eleves', function (Blueprint $table) {
            $table->dropColumn(['region_origine', 'departement_origine', 'handicap', 'type_handicap', 'ecole_precedente']);
        });
    }
};
