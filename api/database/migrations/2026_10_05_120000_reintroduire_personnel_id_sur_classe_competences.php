<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * L'enseignant revient sur l'attribution — mais comme une exception, pas
     * comme la règle. Par défaut la colonne reste nulle : la compétence est
     * tenue par le titulaire de la classe, et c'est encore lui qui la saisit.
     * La renseigner confie la compétence à un enseignant qui n'est pas
     * titulaire — un intervenant d'anglais ou de sport sur une classe qu'il ne
     * tient pas.
     *
     * La colonne avait été retirée en août (cf. la migration
     * `retirer_personnel_id_de_classe_competences`) parce qu'elle doublonnait
     * alors `classe_matieres.personnel_id`. Ce n'est plus le cas : une
     * compétence sans matière rattachée — le cas courant du référentiel
     * officiel — n'a aucun autre endroit où porter son responsable.
     */
    public function up(): void
    {
        Schema::table('classe_competences', function (Blueprint $table) {
            $table->foreignId('personnel_id')
                ->nullable()
                ->after('competence_id')
                ->constrained('personnels')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('classe_competences', function (Blueprint $table) {
            $table->dropForeign(['personnel_id']);
            $table->dropColumn('personnel_id');
        });
    }
};
