<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * L'enseignant n'est plus porté par la compétence mais par chaque matière
     * (`classe_matieres.personnel_id`) : au primaire comme au secondaire, on
     * affecte un enseignant par matière. La saisie des notes reste ouverte au
     * seul titulaire de la classe (`Classe.titulaire_id`), indépendamment de
     * cette colonne.
     *
     * Aucune perte de données : les `classe_matieres` installées en cascade
     * portent déjà la même valeur (vérifié en production, 0 désynchronisation).
     */
    public function up(): void
    {
        Schema::table('classe_competences', function (Blueprint $table) {
            $table->dropForeign(['personnel_id']);
            $table->dropColumn('personnel_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('classe_competences', function (Blueprint $table) {
            $table->foreignId('personnel_id')->nullable()->after('competence_id')->constrained('personnels')->nullOnDelete();
        });
    }
};
