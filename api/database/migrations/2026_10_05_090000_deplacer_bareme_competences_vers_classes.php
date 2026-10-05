<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Le barème et les volets quittent la compétence pour son attribution à une
 * classe.
 *
 * Jusqu'ici « Langue et communication » était notée sur le même barème, et
 * répartie sur les mêmes volets, dans toutes les classes où elle était
 * enseignée — la colonne vivait sur `competences`. Or c'est faux dès qu'un
 * établissement monte en niveau : la même compétence pèse 20 au CM2 et 10 au
 * CP, et le volet pratique n'est évalué que là où la classe le travaille.
 *
 * Les trois colonnes descendent donc d'un cran, sur `classe_competences` :
 * la compétence ne garde que son identité (libellés, abréviation, ordre), et
 * c'est l'attribution qui dit comment on la note ici. Les notes déjà saisies
 * ne bougent pas — elles pendent déjà à `classe_competences.id`.
 *
 * Les valeurs existantes sont recopiées sur chaque attribution avant que les
 * colonnes ne soient retirées : un établissement en cours d'année retrouve
 * exactement ses barèmes, classe par classe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classe_competences', function (Blueprint $table) {
            // Mêmes définitions que sur `competences` : `notation` reste
            // nullable (la maternelle évalue par appréciation, sans barème).
            $table->unsignedSmallInteger('notation')->nullable()->after('competence_id');
            $table->boolean('evalue_pratique')->default(false)->after('notation');
            $table->json('repartition_volets')->nullable()->after('evalue_pratique');
        });

        // Recopie compétence -> attributions. Une jointure plutôt qu'une boucle
        // PHP : l'opération doit tenir sur un établissement entier sans charger
        // la table en mémoire.
        foreach (DB::table('competences')->select('id', 'notation', 'evalue_pratique', 'repartition_volets')->cursor() as $competence) {
            DB::table('classe_competences')
                ->where('competence_id', $competence->id)
                ->update([
                    'notation' => $competence->notation,
                    'evalue_pratique' => $competence->evalue_pratique,
                    'repartition_volets' => $competence->repartition_volets,
                ]);
        }

        Schema::table('competences', function (Blueprint $table) {
            $table->dropColumn(['notation', 'evalue_pratique', 'repartition_volets']);
        });
    }

    public function down(): void
    {
        Schema::table('competences', function (Blueprint $table) {
            $table->unsignedSmallInteger('notation')->nullable()->after('abbreviation');
            $table->boolean('evalue_pratique')->default(false)->after('notation');
            $table->json('repartition_volets')->nullable()->after('evalue_pratique');
        });

        // Remontée : la première attribution renseignée fait foi. Le rollback
        // ramène à un barème unique par compétence, ce qui est précisément la
        // limite que cette migration lève — des barèmes devenus distincts
        // entre classes ne peuvent pas être conservés.
        foreach (DB::table('classe_competences')
            ->select('competence_id', 'notation', 'evalue_pratique', 'repartition_volets')
            ->orderBy('id')
            ->cursor() as $attribution) {
            DB::table('competences')
                ->where('id', $attribution->competence_id)
                ->whereNull('notation')
                ->update([
                    'notation' => $attribution->notation,
                    'evalue_pratique' => $attribution->evalue_pratique,
                    'repartition_volets' => $attribution->repartition_volets,
                ]);
        }

        Schema::table('classe_competences', function (Blueprint $table) {
            $table->dropColumn(['notation', 'evalue_pratique', 'repartition_volets']);
        });
    }
};
