<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Budget alloué à un membre du personnel : une enveloppe qu'il gère lui-même
 * sous sa responsabilité, plutôt que de faire passer chaque dépense par la
 * caisse centrale. Le solde ne se stocke pas : il se déduit toujours des
 * dépenses imputées (`depenses.budget_personnel_id`), comme le solde d'une
 * avance sur salaire se déduit de ses remboursements.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets_personnel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('personnel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('annee_scolaire_id')->nullable()->constrained('annee_scolaires')->nullOnDelete();
            $table->string('libelle');
            $table->unsignedBigInteger('montant_alloue');
            $table->date('date_allocation');
            // Ce que le personnel répond à « comment gérez-vous ce budget ? » —
            // modifiable par lui-même depuis son espace, pas seulement à l'allocation.
            $table->text('note_gestion')->nullable();
            $table->foreignId('alloue_par')->nullable()->constrained('users')->nullOnDelete();
            // Un budget clôturé reste au registre, neutralisé — jamais supprimé,
            // même règle que les avances sur salaire et les versements.
            $table->timestamp('annule_le')->nullable();
            $table->foreignId('annule_par')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motif_annulation')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'personnel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets_personnel');
    }
};
