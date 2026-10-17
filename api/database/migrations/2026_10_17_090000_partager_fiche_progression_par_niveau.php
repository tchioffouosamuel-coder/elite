<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fiche de progression partagée par matière + niveau (BUG1 — punch-list
 * Elites-tech) : un enseignant qui donne la même matière, au même niveau,
 * dans plusieurs classes ne la remplit plus qu'une fois.
 *
 * `classe_matiere_id` devient nullable : une ligne « partagée » n'appartient
 * à aucune affectation précise, elle se retrouve par le triplet
 * (matière, niveau, enseignant) porté par les quatre colonnes ajoutées ici.
 *
 * Le niveau se lit comme ailleurs dans l'app (cf. `ConseilClasseService`,
 * `ArchivageService`) : `niveau_scolaire_id` pour le primaire/maternelle
 * (SIL, CP…), `niveau_classe` (le libellé libre, ex. « 6ème ») sinon — une
 * classe qui ne porte ni l'un ni l'autre n'a pas de niveau identifiable et
 * reste forcément en mode historique (cf. `ResolveurFicheProgression`).
 *
 * Les lignes historiques existantes ne sont ni déplacées ni fusionnées : une
 * fiche déjà saisie pour une affectation précise continue de se lire et
 * s'écrire par `classe_matiere_id`, inchangée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('progression_colonnes', function (Blueprint $table) {
            $table->foreignId('classe_matiere_id')->nullable()->change();
            $table->foreignId('matiere_id')->nullable()->after('classe_matiere_id')
                ->constrained('matieres')->nullOnDelete();
            $table->foreignId('niveau_scolaire_id')->nullable()->after('matiere_id')
                ->constrained('niveau_scolaires')->nullOnDelete();
            $table->string('niveau_classe', 100)->nullable()->after('niveau_scolaire_id');
            $table->foreignId('personnel_id')->nullable()->after('niveau_classe')
                ->constrained('personnels')->nullOnDelete();

            $table->index(['matiere_id', 'niveau_scolaire_id', 'personnel_id'], 'progression_colonnes_partage_scolaire_idx');
            $table->index(['matiere_id', 'niveau_classe', 'personnel_id'], 'progression_colonnes_partage_classe_idx');
        });

        Schema::table('progression_items', function (Blueprint $table) {
            $table->foreignId('classe_matiere_id')->nullable()->change();
            $table->foreignId('matiere_id')->nullable()->after('classe_matiere_id')
                ->constrained('matieres')->nullOnDelete();
            $table->foreignId('niveau_scolaire_id')->nullable()->after('matiere_id')
                ->constrained('niveau_scolaires')->nullOnDelete();
            $table->string('niveau_classe', 100)->nullable()->after('niveau_scolaire_id');
            $table->foreignId('personnel_id')->nullable()->after('niveau_classe')
                ->constrained('personnels')->nullOnDelete();

            $table->index(['matiere_id', 'niveau_scolaire_id', 'personnel_id'], 'progression_items_partage_scolaire_idx');
            $table->index(['matiere_id', 'niveau_classe', 'personnel_id'], 'progression_items_partage_classe_idx');
        });
    }

    public function down(): void
    {
        Schema::table('progression_items', function (Blueprint $table) {
            $table->dropIndex('progression_items_partage_scolaire_idx');
            $table->dropIndex('progression_items_partage_classe_idx');
            $table->dropConstrainedForeignId('matiere_id');
            $table->dropConstrainedForeignId('niveau_scolaire_id');
            $table->dropConstrainedForeignId('personnel_id');
            $table->dropColumn('niveau_classe');
            $table->foreignId('classe_matiere_id')->nullable(false)->change();
        });

        Schema::table('progression_colonnes', function (Blueprint $table) {
            $table->dropIndex('progression_colonnes_partage_scolaire_idx');
            $table->dropIndex('progression_colonnes_partage_classe_idx');
            $table->dropConstrainedForeignId('matiere_id');
            $table->dropConstrainedForeignId('niveau_scolaire_id');
            $table->dropConstrainedForeignId('personnel_id');
            $table->dropColumn('niveau_classe');
            $table->foreignId('classe_matiere_id')->nullable(false)->change();
        });
    }
};
