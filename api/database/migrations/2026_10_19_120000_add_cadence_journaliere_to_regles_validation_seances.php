<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cadence quotidienne attendue d'un enseignant, ajoutée à la règle de
 * validation des séances (cf. `RegleValidationSeance`).
 *
 * 0 — la valeur par défaut — signifie « pas de cadence journalière » : chaque
 * cours se déclare séparément, comme aujourd'hui, et rien ne change au
 * secondaire. Une valeur non nulle active le mode cadence : au primaire et à
 * la maternelle, où le même enseignant tient sa classe du matin au soir, la
 * direction attend deux déclarations et deux appels par jour, pas un par
 * créneau de la grille. Une fois ce compte atteint, la journée est réputée
 * faite — les séances restantes du jour passent en « effectuée » et les
 * leçons planifiées ce jour-là sont cochées.
 *
 * Le défaut à 0 est délibéré : à 1, la toute première déclaration de la
 * journée clôturerait d'office tous les cours suivants d'un enseignant du
 * secondaire, qui en donne pourtant un par classe.
 *
 * Les valeurs sont par école (+ sous-système), donc modifiables par la
 * direction sans toucher au code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regles_validation_seances', function (Blueprint $table) {
            $table->unsignedTinyInteger('validations_par_jour')->default(0)->after('delai_unite');
            $table->unsignedTinyInteger('appels_par_jour')->default(0)->after('validations_par_jour');
        });
    }

    public function down(): void
    {
        Schema::table('regles_validation_seances', function (Blueprint $table) {
            $table->dropColumn(['validations_par_jour', 'appels_par_jour']);
        });
    }
};
