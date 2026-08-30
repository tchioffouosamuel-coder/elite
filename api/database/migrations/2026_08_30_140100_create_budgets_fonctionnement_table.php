<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Montant perçu par rubrique du budget de fonctionnement (tableau 21 du
     * rapport de rentrée MINEDUB) — le « dépensé » se déduit des dépenses
     * taguées sur la même rubrique (`depenses.rubrique_budget_fonctionnement`),
     * jamais stocké ici pour ne pas désynchroniser les deux.
     */
    public function up(): void
    {
        Schema::create('budgets_fonctionnement', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('annee_scolaire_id')->constrained('annee_scolaires')->cascadeOnDelete();
            $table->enum('rubrique', ['primes_rendement', 'projet_ecole', 'fenassco', 'fonctionnement', 'evaluation']);
            $table->unsignedBigInteger('montant_percu')->default(0);
            $table->text('observations')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'annee_scolaire_id', 'rubrique'], 'budgets_fonctionnement_ecole_annee_rubrique_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets_fonctionnement');
    }
};
