<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Au secondaire une séquence donne une note unique par matière : la
     * composante vaut 'unique' et la contrainte d'unicité historique tient.
     *
     * Au primaire la même séquence produit une note par volet d'évaluation
     * (oral, écrit, savoir-être, éventuellement pratique) — la composante
     * entre donc dans la clé d'unicité. On stocke 'unique' plutôt que NULL
     * pour le secondaire, MySQL considérant NULL comme distinct dans un
     * index unique (la contrainte ne s'appliquerait plus).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('notes', 'composante')) {
            Schema::table('notes', function (Blueprint $table) {
                $table->enum('composante', ['unique', 'oral', 'ecrit', 'savoir_etre', 'pratique'])
                    ->default('unique')->after('sequence_id');
            });
        }

        // L'index unique historique porte la clé étrangère sur eleve_id : il faut
        // créer son remplaçant (même colonne en tête) avant de pouvoir le supprimer.
        Schema::table('notes', function (Blueprint $table) {
            $table->unique(['eleve_id', 'classe_matiere_id', 'sequence_id', 'composante'], 'notes_unique_cellule');
        });

        Schema::table('notes', function (Blueprint $table) {
            $table->dropUnique('notes_unique_cell');
        });

        // Le barème du primaire va jusqu'à 100 : decimal(4,2) plafonnait à 99.99.
        Schema::table('notes', function (Blueprint $table) {
            $table->decimal('valeur', 5, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->unique(['eleve_id', 'classe_matiere_id', 'sequence_id'], 'notes_unique_cell');
        });

        Schema::table('notes', function (Blueprint $table) {
            $table->dropUnique('notes_unique_cellule');
            $table->dropColumn('composante');
            $table->decimal('valeur', 4, 2)->nullable()->change();
        });
    }
};
