<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Registre des documents officiels émis par l'établissement (certificats,
        // attestations…) : chacun reçoit un numéro d'ordre séquentiel, tenu par
        // le système plutôt que sur un registre papier — un même numéro ne doit
        // jamais être attribué deux fois pour un même type de document, une même
        // école et une même année scolaire.
        Schema::create('document_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            // Identifie le générateur (ex: 'certificat_scolarite') : un simple
            // code, pas de table de types à gérer pour ajouter un document.
            $table->string('type');
            $table->foreignId('annee_scolaire_id')->nullable()->constrained('annee_scolaires')->nullOnDelete();
            $table->unsignedInteger('numero');
            // Élève, personnel… concerné par le document — facultatif, certains
            // documents (fiche du personnel, liste de classe) ne visent personne
            // en particulier.
            $table->nullableMorphs('referencable');
            $table->foreignId('genere_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'type', 'annee_scolaire_id', 'numero'], 'document_references_unique_numero');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_references');
    }
};
