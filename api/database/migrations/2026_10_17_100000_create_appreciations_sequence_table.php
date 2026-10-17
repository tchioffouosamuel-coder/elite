<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Point 16 de la punch-list : une appréciation courte (60 caractères), par
 * élève et par matière (secondaire) ou compétence (primaire/maternelle),
 * saisie séquence par séquence — et non par trimestre comme
 * `ObservationEvaluation` — puisque seules les séquences 1 et 2 en portent
 * une, affichées ensemble sur le bulletin du premier trimestre.
 *
 * Même paire de clés nullables que `observations_evaluations` : la matière
 * sert au secondaire, la compétence au primaire/maternelle, jamais les deux
 * à la fois pour une même ligne.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appreciations_sequence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->foreignId('classe_matiere_id')->nullable()->constrained('classe_matieres')->cascadeOnDelete();
            $table->foreignId('classe_competence_id')->nullable()->constrained('classe_competences')->cascadeOnDelete();
            $table->foreignId('sequence_id')->constrained('sequences')->cascadeOnDelete();
            $table->string('texte', 60)->nullable();
            $table->foreignId('saisi_par')->nullable()->constrained('personnels')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['eleve_id', 'classe_matiere_id', 'sequence_id'],
                'appreciations_sequence_matiere_unique',
            );
            $table->unique(
                ['eleve_id', 'classe_competence_id', 'sequence_id'],
                'appreciations_sequence_competence_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appreciations_sequence');
    }
};
