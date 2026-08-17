<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Avances sur salaire : un employé qui perçoit une avance rembourse ensuite
 * par tranches (retenue sur salaire ou versement direct), comme une famille
 * solde un dossier de scolarité par versements successifs — même schéma
 * en-tête + lignes que `dossiers_scolarite` / `versements`, appliqué au
 * personnel plutôt qu'aux élèves.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avances_salaire', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('personnel_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('montant');
            $table->date('date_avance');
            $table->string('motif')->nullable();
            $table->foreignId('accordee_par')->nullable()->constrained('users')->nullOnDelete();
            // Une avance annulée reste au registre, neutralisée — jamais supprimée,
            // même règle que les versements de scolarité.
            $table->timestamp('annule_le')->nullable();
            $table->foreignId('annule_par')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motif_annulation')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'personnel_id']);
        });

        Schema::create('avance_remboursements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('avance_salaire_id')->constrained('avances_salaire')->cascadeOnDelete();
            $table->unsignedInteger('montant');
            $table->date('date_remboursement');
            $table->enum('mode', ['retenue_salaire', 'versement_direct'])->default('retenue_salaire');
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avance_remboursements');
        Schema::dropIfExists('avances_salaire');
    }
};
