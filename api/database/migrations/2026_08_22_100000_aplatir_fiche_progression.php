<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La progression devient la fiche de préparation.
 *
 * L'enseignant remplissait jusqu'ici deux écrans : créer les leçons dans le
 * programme, puis rouvrir chacune pour la préparer. La feuille de progression
 * de l'établissement ne connaît pas ce détour — une ligne par leçon porte tout,
 * du résultat attendu aux activités du facilitateur. C'est ce que cette
 * migration installe.
 *
 * Les trois « Stage: » passent de JSON à texte. Ils portaient chacun un objet
 * {main_points_of_matter, learners_activities, facilitators_activities}, alors
 * que la feuille les traite en colonnes indépendantes — aucune fusion
 * d'en-tête ne les regroupe en ligne 6. Le contenu éventuel est recopié en
 * texte plutôt que perdu.
 */
return new class extends Migration
{
    /** Les trois étapes de la fiche, dans leur ordre de déroulement. */
    private const STAGES = ['introduction', 'presentation', 'conclusion'];

    public function up(): void
    {
        Schema::table('progression_items', function (Blueprint $table) {
            // Les champs de la fiche qui manquaient à l'appel.
            $table->text('expected_learning_outcomes')->nullable()->after('description');
            $table->text('stages_of_lesson')->nullable()->after('research_questions');
            $table->text('main_points')->nullable()->after('conclusion');
            $table->text('learners_activities')->nullable()->after('main_points');
            $table->text('facilitators_activities')->nullable()->after('learners_activities');

            /*
             * Repères de calendrier de la feuille. Les horaires, l'appel et le
             * visa n'y figurent pas : ils relèvent de la séance réellement
             * tenue, que l'emploi du temps et la feuille d'appel gèrent déjà.
             */
            $table->string('term', 40)->nullable()->after('facilitators_activities');
            $table->string('mois', 20)->nullable()->after('term');
            $table->string('semaine', 20)->nullable()->after('mois');
            $table->date('date_prevue')->nullable()->after('semaine');
        });

        // Colonnes de transit : le contenu JSON y est aplati en texte avant que
        // les colonnes d'origine ne soient retypées.
        Schema::table('progression_items', function (Blueprint $table) {
            foreach (self::STAGES as $stage) {
                $table->text($stage.'_texte')->nullable();
            }
        });

        foreach (self::STAGES as $stage) {
            foreach (DB::table('progression_items')->whereNotNull($stage)->get(['id', $stage]) as $ligne) {
                $brut = json_decode((string) $ligne->{$stage}, true);

                $texte = is_array($brut)
                    ? trim(implode("\n", array_filter([
                        $brut['main_points_of_matter'] ?? null,
                        $brut['learners_activities'] ?? null,
                        $brut['facilitators_activities'] ?? null,
                    ])))
                    : trim((string) $ligne->{$stage});

                DB::table('progression_items')
                    ->where('id', $ligne->id)
                    ->update([$stage.'_texte' => $texte !== '' ? $texte : null]);
            }
        }

        Schema::table('progression_items', function (Blueprint $table) {
            $table->dropColumn(self::STAGES);
        });

        Schema::table('progression_items', function (Blueprint $table) {
            foreach (self::STAGES as $stage) {
                $table->renameColumn($stage.'_texte', $stage);
            }
        });
    }

    public function down(): void
    {
        Schema::table('progression_items', function (Blueprint $table) {
            $table->dropColumn([
                'expected_learning_outcomes', 'stages_of_lesson', 'main_points',
                'learners_activities', 'facilitators_activities',
                'term', 'mois', 'semaine', 'date_prevue',
            ]);
        });

        // Le retour à JSON ne restaure pas la découpe d'origine : le texte
        // aplati n'a plus de quoi la reconstituer.
        Schema::table('progression_items', function (Blueprint $table) {
            $table->dropColumn(self::STAGES);
        });

        Schema::table('progression_items', function (Blueprint $table) {
            foreach (self::STAGES as $stage) {
                $table->json($stage)->nullable();
            }
        });
    }
};
