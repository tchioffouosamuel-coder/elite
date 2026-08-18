<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Trace des suppressions, pour que la synchronisation mobile puisse les
     * répercuter.
     *
     * Une ligne supprimée disparaît sans laisser d'indice : un client qui
     * demande « qu'est-ce qui a changé depuis mardi ? » ne peut pas deviner
     * qu'un élève a été retiré, et le garderait indéfiniment dans sa base
     * locale. On enregistre donc la disparition ici.
     *
     * Choisi plutôt qu'activer `SoftDeletes` sur les modèles concernés :
     * ajouter `deleted_at` changerait silencieusement le résultat de toutes
     * les requêtes déjà écrites côté web, alors que cette table est purement
     * additive.
     */
    public function up(): void
    {
        Schema::create('sync_tombstones', function (Blueprint $table) {
            $table->id();
            // Clé d'entité du registre (`RegistreSync`), pas le nom de classe :
            // renommer un modèle ne doit pas invalider les curseurs des clients.
            $table->string('entite', 60);
            $table->unsignedBigInteger('entite_id');
            // Permet de ne renvoyer que les suppressions de l'établissement
            // courant. Nullable : quelques référentiels sont globaux.
            $table->foreignId('school_id')->nullable()->constrained('schools')->cascadeOnDelete();
            $table->timestamp('supprime_le')->useCurrent();

            // L'index porte l'ordre exact de la requête de synchronisation
            // (école, puis fenêtre temporelle) : c'est la seule lecture que
            // cette table subit, et elle doit rester rapide en grandissant.
            $table->index(['school_id', 'supprime_le']);
            $table->index('supprime_le');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_tombstones');
    }
};
