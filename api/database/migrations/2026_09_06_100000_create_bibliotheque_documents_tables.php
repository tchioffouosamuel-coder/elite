<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bibliothèque numérique : des documents déposés par l'admin, chacun visible
 * par plusieurs écoles à la fois — contrairement à `Annonce` (scopée à une
 * seule école), il faut donc une vraie table pivot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bibliotheque_documents', function (Blueprint $table) {
            $table->id();
            $table->string('titre');
            $table->text('description')->nullable();
            $table->string('fichier_path');
            $table->string('fichier_nom_original');
            $table->unsignedBigInteger('taille');
            $table->string('type_mime')->nullable();
            $table->foreignId('uploaded_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('bibliotheque_document_school', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bibliotheque_document_id')->constrained('bibliotheque_documents')->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['bibliotheque_document_id', 'school_id'], 'bib_doc_school_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bibliotheque_document_school');
        Schema::dropIfExists('bibliotheque_documents');
    }
};
