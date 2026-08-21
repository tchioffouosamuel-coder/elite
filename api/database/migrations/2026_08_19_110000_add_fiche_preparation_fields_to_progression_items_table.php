<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fiche de préparation détaillée (format MINESEC), propre à une leçon.
     * Remplace l'usage des colonnes objectifs/materiel/activites/devoirs,
     * conservées pour ne pas casser les imports/exports existants.
     */
    public function up(): void
    {
        Schema::table('progression_items', function (Blueprint $table) {
            $table->string('topic')->nullable()->after('devoirs');
            $table->string('lesson')->nullable()->after('topic');
            $table->text('competence')->nullable()->after('lesson');
            $table->enum('mode', ['digital', 'practical', 'normal'])->nullable()->after('competence');
            $table->text('entry_behaviour')->nullable()->after('mode');
            $table->text('teaching_aids')->nullable()->after('entry_behaviour');
            $table->text('teaching_learning_strategies')->nullable()->after('teaching_aids');
            $table->text('references')->nullable()->after('teaching_learning_strategies');
            $table->text('research_questions')->nullable()->after('references');
            // Chaque étape (introduction/présentation/conclusion) porte les mêmes
            // trois volets : {main_points_of_matter, learners_activities, facilitators_activities}.
            $table->json('introduction')->nullable()->after('research_questions');
            $table->json('presentation')->nullable()->after('introduction');
            $table->json('conclusion')->nullable()->after('presentation');
        });
    }

    public function down(): void
    {
        Schema::table('progression_items', function (Blueprint $table) {
            $table->dropColumn([
                'topic', 'lesson', 'competence', 'mode', 'entry_behaviour',
                'teaching_aids', 'teaching_learning_strategies', 'references',
                'research_questions', 'introduction', 'presentation', 'conclusion',
            ]);
        });
    }
};
