<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ciblage plus fin des documents de la bibliothèque : au-delà de l'école
 * (déjà géré par `bibliotheque_document_school`), un document peut être
 * restreint à une ou plusieurs classes (ex. seule la classe de CM2-A voit ce
 * document), et/ou à un type de destinataire (`cibles` : personnel, parents).
 * Un document sans classe rattachée reste visible par toute l'école, comme
 * avant ; un document sans `cibles` reste visible par tout le monde.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bibliotheque_documents', function (Blueprint $table) {
            // Null = tous les destinataires (comportement historique).
            $table->json('cibles')->nullable()->after('type_mime');
        });

        Schema::create('bibliotheque_document_classe', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bibliotheque_document_id')->constrained('bibliotheque_documents')->cascadeOnDelete();
            $table->foreignId('classe_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['bibliotheque_document_id', 'classe_id'], 'bib_doc_classe_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bibliotheque_document_classe');

        Schema::table('bibliotheque_documents', function (Blueprint $table) {
            $table->dropColumn('cibles');
        });
    }
};
