<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Préparation des évaluations : questions, barèmes et compétences visées,
     * rattachées à une affectation classe↔matière et, si l'enseignant le
     * précise, à la leçon du programme qu'elles évaluent.
     */
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classe_matiere_id')->constrained('classe_matieres')->cascadeOnDelete();
            $table->foreignId('progression_item_id')->nullable()->constrained('progression_items')->nullOnDelete();
            $table->string('titre');
            $table->enum('type', ['interrogation', 'devoir', 'examen'])->default('interrogation');
            $table->date('date_prevue')->nullable();
            $table->unsignedSmallInteger('bareme')->default(20);
            // Compétences visées, en texte libre séparé par virgules : la liste
            // varie trop d'une matière à l'autre pour justifier un référentiel.
            $table->text('competences')->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('personnels')->nullOnDelete();
            $table->timestamps();
            $table->index(['classe_matiere_id', 'date_prevue']);
        });

        Schema::create('evaluation_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained()->cascadeOnDelete();
            $table->text('enonce');
            $table->unsignedSmallInteger('bareme_question')->default(1);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_questions');
        Schema::dropIfExists('evaluations');
    }
};
