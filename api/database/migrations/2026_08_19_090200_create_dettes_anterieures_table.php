<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dette antérieure saisie à la main — le cas d'un élève qui doit déjà de
     * l'argent avant même l'ouverture de son premier dossier dans ce système
     * (reprise d'une comptabilité antérieure, élève transféré). Distincte du
     * report automatique d'une année à l'autre (`dossiers_scolarite.report_dette`,
     * calculé par `ScolariteService::reliquatAnneePrecedente()`), qui ne
     * trouve rien à reporter la toute première année d'usage de l'application.
     *
     * `imputee_dossier_id` trace le dossier dans lequel la dette a fini par
     * être reprise (cf. ScolariteService::detteAnterieureNonImputee()) : une
     * fois imputée elle ne doit plus s'ajouter une seconde fois si le dossier
     * est relu.
     */
    public function up(): void
    {
        Schema::create('dettes_anterieures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->unsignedBigInteger('montant');
            $table->string('motif')->nullable();
            $table->foreignId('accorde_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('imputee_dossier_id')->nullable()->constrained('dossiers_scolarite')->nullOnDelete();
            $table->timestamps();

            $table->index(['eleve_id', 'imputee_dossier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dettes_anterieures');
    }
};
