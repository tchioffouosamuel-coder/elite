<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Blocs de texte libre du rapport de fin de trimestre MINEDUB :
     * introduction, observations (structure, élèves, personnel), difficultés
     * rencontrées et conclusion générale. Même principe que
     * `rapport_rentree_textes` — une ligne par rubrique plutôt qu'une colonne
     * chacune — mais scopé par trimestre plutôt que par année scolaire : ce
     * rapport se dépose à chaque fin de trimestre, pas une fois par an.
     */
    public function up(): void
    {
        Schema::create('rapport_trimestre_textes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trimestre_id')->constrained('trimestres')->cascadeOnDelete();
            $table->enum('rubrique', [
                'introduction', 'observations_structure', 'observations_eleves',
                'observations_personnel', 'difficultes_rencontrees', 'conclusion_generale',
            ]);
            $table->text('contenu')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'trimestre_id', 'rubrique'], 'rapport_trimestre_textes_ecole_trimestre_rubrique_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapport_trimestre_textes');
    }
};
