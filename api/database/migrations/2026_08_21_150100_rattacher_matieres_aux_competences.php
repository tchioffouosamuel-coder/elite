<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La matière cède la notation à la compétence et devient son contenu.
 *
 * Trois colonnes la quittent — `notation`, `evalue_pratique`,
 * `repartition_volets` — parce qu'elles décrivaient l'évaluation, qui se fait
 * désormais au niveau de la compétence. Ces colonnes n'ont jamais concerné le
 * secondaire (elles sont nées avec le primaire, cf.
 * `add_primaire_columns_to_matieres_table`), son moteur de notes n'y touche pas.
 *
 * `competence_id` reste nullable : une matière du secondaire n'a pas de
 * compétence, et une matière du primaire peut être créée avant d'être rattachée.
 * Supprimer une compétence emporte ses matières — elles n'ont pas de sens sans
 * elle — mais laisse intactes celles du secondaire, qui ne la référencent pas.
 *
 * `notes` accueille `classe_competence_id` : au primaire la note porte sur la
 * compétence, au secondaire elle continue de porter sur l'affectation matière.
 * Une même table, deux chemins, chacun avec sa contrainte d'unicité.
 *
 * PURGE — cette migration supprime les matières du primaire et de la maternelle
 * ainsi que leurs affectations, à la demande explicite de l'établissement
 * (« repartir de zéro ») : les barèmes existants décrivaient des matières, pas
 * des compétences, et les reprendre aurait produit un référentiel faux à
 * corriger ligne à ligne. Aucune note n'existait au moment de la bascule ; les
 * séances et items de progression rattachés partent en cascade. Le secondaire
 * n'est pas touché.
 */
return new class extends Migration
{
    public function up(): void
    {
        // La purge précède l'ajout de la colonne : inutile de rattacher des
        // lignes qui vont disparaître.
        $ecolesConcernees = DB::table('schools')->whereIn('type', ['primaire', 'maternelle'])->pluck('id');

        if ($ecolesConcernees->isNotEmpty()) {
            DB::table('matieres')->whereIn('school_id', $ecolesConcernees)->delete();
        }

        // Gardes `hasColumn`/`hasForeign` plutôt qu'un bloc unique : un poste
        // desktop peut avoir vu cette migration s'exécuter partiellement lors
        // d'un lancement antérieur interrompu (processus PHP tué en cours de
        // route — panne électrique, antivirus, fermeture forcée) sans que la
        // ligne correspondante soit inscrite dans `migrations` (SQLite ne
        // garantit l'atomicité globale de la migration que si tout tient dans
        // la même transaction, ce qui n'a pas été le cas sur au moins un poste
        // client — observé en conditions réelles : colonne déjà là, migration
        // rejouée depuis le début). Chaque étape se protège donc elle-même
        // pour que relancer la migration après une panne la termine au lieu
        // de la faire échouer sur ce qu'elle avait déjà accompli.
        if (! Schema::hasColumn('matieres', 'competence_id')) {
            Schema::table('matieres', function (Blueprint $table) {
                $table->foreignId('competence_id')->nullable()->after('abbreviation')
                    ->constrained('competences')->cascadeOnDelete();
            });
        }

        $colonnesMatieresAEnlever = array_values(array_filter(
            ['notation', 'evalue_pratique', 'repartition_volets'],
            fn (string $colonne) => Schema::hasColumn('matieres', $colonne),
        ));

        if ($colonnesMatieresAEnlever !== []) {
            Schema::table('matieres', function (Blueprint $table) use ($colonnesMatieresAEnlever) {
                $table->dropColumn($colonnesMatieresAEnlever);
            });
        }

        if (! Schema::hasColumn('notes', 'classe_competence_id')) {
            Schema::table('notes', function (Blueprint $table) {
                $table->foreignId('classe_competence_id')->nullable()->after('classe_matiere_id')
                    ->constrained('classe_competences')->cascadeOnDelete();
            });
        }

        // La note du primaire ne porte plus d'affectation matière : la colonne
        // doit accepter NULL. Il faut lâcher la clé étrangère le temps du
        // changement de type, MySQL refusant de modifier une colonne indexée
        // par une contrainte référentielle.
        $aDejaLaContrainteClasseMatiere = collect(Schema::getForeignKeys('notes'))
            ->contains(fn (array $cle) => $cle['columns'] === ['classe_matiere_id']);

        if ($aDejaLaContrainteClasseMatiere) {
            Schema::table('notes', function (Blueprint $table) {
                $table->dropForeign(['classe_matiere_id']);
            });
        }

        Schema::table('notes', function (Blueprint $table) {
            $table->unsignedBigInteger('classe_matiere_id')->nullable()->change();
        });

        $aDejaLIndexUniqueCompetence = collect(Schema::getIndexes('notes'))
            ->contains(fn (array $index) => $index['name'] === 'notes_unique_cellule_competence');

        Schema::table('notes', function (Blueprint $table) use ($aDejaLIndexUniqueCompetence) {
            if (! collect(Schema::getForeignKeys('notes'))->contains(fn (array $cle) => $cle['columns'] === ['classe_matiere_id'])) {
                $table->foreign('classe_matiere_id')->references('id')->on('classe_matieres')->cascadeOnDelete();
            }

            // Une contrainte par chemin. MySQL considère NULL comme distinct
            // dans un index unique : chaque index ne contraint donc que les
            // lignes de son propre moteur de notation, sans gêner l'autre.
            if (! $aDejaLIndexUniqueCompetence) {
                $table->unique(
                    ['eleve_id', 'classe_competence_id', 'sequence_id', 'composante'],
                    'notes_unique_cellule_competence',
                );
            }
        });
    }

    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropUnique('notes_unique_cellule_competence');
            $table->dropConstrainedForeignId('classe_competence_id');
        });

        Schema::table('matieres', function (Blueprint $table) {
            $table->dropConstrainedForeignId('competence_id');
            $table->unsignedSmallInteger('notation')->nullable()->after('abbreviation');
            $table->boolean('evalue_pratique')->default(false)->after('notation');
            $table->json('repartition_volets')->nullable()->after('evalue_pratique');
        });

        // Les matières supprimées ne reviennent pas : la purge est assumée.
    }
};
