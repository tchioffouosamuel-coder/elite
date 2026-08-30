<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Une dépense peut désormais s'imputer sur un budget alloué au personnel,
 * plutôt que sur la caisse ou le revenu personnel de quelqu'un — troisième
 * valeur de `source`, au même titre que les deux premières.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('depenses', function (Blueprint $table) {
            $table->foreignId('budget_personnel_id')->nullable()->after('vehicule_id')
                ->constrained('budgets_personnel')->nullOnDelete();
        });

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->recreerTable(true);

            return;
        }

        DB::statement(
            "ALTER TABLE depenses MODIFY source ENUM('caisse', 'revenu_personnel', 'budget_personnel') DEFAULT 'caisse'"
        );
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            // La table recréée sans la colonne règle aussi la clé étrangère.
            $this->recreerTable(false);

            return;
        }

        DB::statement(
            "ALTER TABLE depenses MODIFY source ENUM('caisse', 'revenu_personnel') DEFAULT 'caisse'"
        );

        Schema::table('depenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('budget_personnel_id');
        });
    }

    /**
     * Sqlite ne sait pas modifier un CHECK d'enum en place : on reconstruit la
     * colonne `source` avec la nouvelle liste de valeurs, comme le fait déjà
     * `2026_08_17_200100_add_bus_to_versement_lignes_affectation.php`.
     */
    private function recreerTable(bool $avecBudget): void
    {
        Schema::disableForeignKeyConstraints();

        // Sans ce pragma, sqlite réécrit la référence de la clé étrangère
        // `immobilisations.depense_id` pour suivre le renommage ci-dessous
        // (vers `depenses_legacy`) — puis la laisse pendante une fois cette
        // table supprimée. Avec lui, la référence reste au nom `depenses`,
        // que la table recréée plus bas porte de nouveau.
        DB::statement('PRAGMA legacy_alter_table = ON');
        Schema::rename('depenses', 'depenses_legacy');

        // Sqlite ne renomme pas l'index avec la table : sans ce drop, la
        // création ci-dessous entrerait en conflit avec l'index resté sur
        // `depenses_legacy` (les noms d'index sont globaux à la base).
        Schema::table('depenses_legacy', function (Blueprint $table) {
            $table->dropIndex('depenses_school_id_date_depense_index');
        });

        Schema::create('depenses', function (Blueprint $table) use ($avecBudget) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('annee_scolaire_id')->nullable()->constrained('annee_scolaires')->nullOnDelete();
            $table->foreignId('compte_comptable_id')->nullable()->constrained('comptes_comptables')->nullOnDelete();
            $table->foreignId('vehicule_id')->nullable()->constrained('bus_vehicules')->nullOnDelete();
            if ($avecBudget) {
                $table->foreignId('budget_personnel_id')->nullable()->constrained('budgets_personnel')->nullOnDelete();
            }
            $table->date('date_depense');
            $table->string('libelle');
            $table->unsignedBigInteger('montant');
            $table->enum('source', $avecBudget
                ? ['caisse', 'revenu_personnel', 'budget_personnel']
                : ['caisse', 'revenu_personnel'])->default('caisse');
            $table->enum('mode', ['especes', 'mobile_money', 'virement', 'cheque', 'depot_bancaire'])->default('especes');
            $table->string('beneficiaire')->nullable();
            $table->string('reference_facture')->nullable();
            $table->string('responsable')->nullable();
            $table->foreignId('saisi_par')->nullable()->constrained('users')->nullOnDelete();
            $table->string('justificatif_path')->nullable();
            $table->enum('statut', ['engagee', 'payee', 'annulee'])->default('payee');
            $table->timestamp('annule_le')->nullable();
            $table->foreignId('annule_par')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motif_annulation')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'date_depense']);
        });

        $colonneBudget = $avecBudget ? 'budget_personnel_id, ' : '';

        DB::statement(
            'INSERT INTO depenses (id, school_id, annee_scolaire_id, compte_comptable_id, vehicule_id, ' . $colonneBudget
                . 'date_depense, libelle, montant, source, mode, beneficiaire, reference_facture, responsable, saisi_par, '
                . 'justificatif_path, statut, annule_le, annule_par, motif_annulation, created_at, updated_at) '
                . 'SELECT id, school_id, annee_scolaire_id, compte_comptable_id, vehicule_id, ' . $colonneBudget
                . 'date_depense, libelle, montant, source, mode, beneficiaire, reference_facture, responsable, saisi_par, '
                . 'justificatif_path, statut, annule_le, annule_par, motif_annulation, created_at, updated_at '
                . 'FROM depenses_legacy'
        );

        Schema::drop('depenses_legacy');
        DB::statement('PRAGMA legacy_alter_table = OFF');
        Schema::enableForeignKeyConstraints();
    }
};
