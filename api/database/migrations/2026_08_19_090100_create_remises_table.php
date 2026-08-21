<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remises individuelles sur la scolarité — plusieurs lignes motivées et
     * datées par élève et par année, plutôt qu'un seul entier négocié sur le
     * dossier (`dossiers_scolarite.remise`, conservé mais désormais dérivé de
     * la somme de ces lignes : cf. ScolariteService::remiseAccordee()).
     */
    public function up(): void
    {
        Schema::create('remises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->foreignId('annee_scolaire_id')->constrained('annee_scolaires')->cascadeOnDelete();
            $table->unsignedBigInteger('montant');
            $table->string('motif')->nullable();
            $table->foreignId('accorde_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['eleve_id', 'annee_scolaire_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remises');
    }
};
