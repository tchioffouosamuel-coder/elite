<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La fiche de progression suit désormais les deux gabarits de
 * l'établissement — un pour maternelle/primaire, un pour le secondaire —
 * au lieu du format MINESEC à seize champs.
 *
 * Les deux gabarits partagent l'essentiel de leurs colonnes (Topic,
 * Sub-topic, Learning Outcomes, Entry Behaviour, Teaching Aids, activités
 * élève/enseignant, Assessment, Assignment, Remarks…) : une seule table les
 * porte tous, chaque écran n'affichant que les colonnes de son cycle
 * (`competence` pour le primaire, `teaching_learning_strategies` pour le
 * secondaire — cf. `ProgressionItem::COLONNES_PAR_CYCLE`).
 *
 * Les colonnes du format MINESEC disparaissent avec lui ; le module de
 * préparation venait de changer de forme cette semaine, sans qu'aucune fiche
 * n'ait encore été saisie en production.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('progression_items', function (Blueprint $table) {
            // Format MINESEC, remplacé par les gabarits de l'établissement.
            $table->dropColumn([
                'objectifs', 'materiel', 'activites', 'devoirs',
                'lesson', 'mode', 'stages_of_lesson', 'references', 'research_questions',
                'introduction', 'presentation', 'conclusion', 'main_points',
                'term', 'mois',
            ]);
        });

        Schema::table('progression_items', function (Blueprint $table) {
            // Sub-topic complète Topic ; Date Taught distingue la date prévue
            // (déjà `date_prevue`) de la date réellement tenue, saisie à la
            // main — indépendante du pointage automatique via les séances.
            $table->string('sous_topic')->nullable()->after('topic');
            $table->date('date_realisee')->nullable()->after('date_prevue');

            // « Duration » (primaire, ex. "40 min") et « Periods » (secondaire,
            // ex. "3") occupent la même case du gabarit ; un seul champ texte
            // porte les deux, l'écran choisissant le libellé selon le cycle.
            $table->string('duree')->nullable()->after('date_realisee');

            $table->text('assessment')->nullable()->after('learners_activities');
            $table->text('assignment')->nullable()->after('assessment');
            $table->text('remarks')->nullable()->after('assignment');

            /*
             * Jusqu'à dix colonnes propres à la matière (cf. table
             * `progression_colonnes`) : {colonne_id: valeur}. Un JSON plutôt
             * que dix colonnes nullable — la plupart des matières n'en
             * définiront aucune, et le nombre exact est ouvert, pas fixé
             * d'avance par le schéma.
             */
            $table->json('colonnes_libres')->nullable()->after('remarks');
        });

        Schema::table('classe_matieres', function (Blueprint $table) {
            // Cartouche du gabarit secondaire : une fois par affectation, pas
            // par leçon. Le Department s'y déduit du département de la
            // matière (cf. Matiere::departement) et n'a donc pas sa colonne.
            $table->string('module_competence')->nullable()->after('competences');
            $table->string('specialite')->nullable()->after('module_competence');
        });
    }

    public function down(): void
    {
        Schema::table('classe_matieres', function (Blueprint $table) {
            $table->dropColumn(['module_competence', 'specialite']);
        });

        Schema::table('progression_items', function (Blueprint $table) {
            $table->dropColumn([
                'sous_topic', 'date_realisee', 'duree', 'assessment', 'assignment', 'remarks', 'colonnes_libres',
            ]);
        });

        Schema::table('progression_items', function (Blueprint $table) {
            $table->text('objectifs')->nullable()->after('description');
            $table->text('materiel')->nullable()->after('objectifs');
            $table->text('activites')->nullable()->after('materiel');
            $table->text('devoirs')->nullable()->after('activites');
            $table->string('lesson')->nullable()->after('topic');
            $table->enum('mode', ['digital', 'practical', 'normal'])->nullable()->after('competence');
            $table->text('stages_of_lesson')->nullable()->after('mode');
            $table->text('references')->nullable()->after('teaching_learning_strategies');
            $table->text('research_questions')->nullable()->after('references');
            $table->text('introduction')->nullable()->after('research_questions');
            $table->text('presentation')->nullable()->after('introduction');
            $table->text('conclusion')->nullable()->after('presentation');
            $table->text('main_points')->nullable()->after('conclusion');
            $table->string('term', 40)->nullable()->after('facilitators_activities');
            $table->string('mois', 20)->nullable()->after('term');
        });
    }
};
