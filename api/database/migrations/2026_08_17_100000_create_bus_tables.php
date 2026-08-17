<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transport scolaire : la flotte (véhicules), les trajets qu'elle dessert
 * (avec leurs arrêts ordonnés), et l'affectation des élèves à un trajet et un
 * arrêt précis. Un véhicule ou un arrêt manquant ne doit jamais empêcher de
 * consulter un trajet ou une affectation déjà enregistrée — d'où des clés
 * étrangères annulées plutôt que bloquantes en dehors du rattachement à
 * l'école, qui reste la seule cascade en dur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bus_vehicules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('immatriculation');
            $table->string('marque')->nullable();
            $table->unsignedSmallInteger('capacite')->default(30);
            $table->foreignId('chauffeur_id')->nullable()->constrained('personnels')->nullOnDelete();
            $table->enum('statut', ['actif', 'hors_service'])->default('actif');
            $table->timestamps();

            $table->unique(['school_id', 'immatriculation']);
        });

        Schema::create('bus_trajets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicule_id')->nullable()->constrained('bus_vehicules')->nullOnDelete();
            $table->string('nom');
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['school_id']);
        });

        Schema::create('bus_arrets', function (Blueprint $table) {
            $table->id();
            // Un arrêt n'a aucun sens détaché de son trajet : ici seulement,
            // la cascade porte sur le trajet plutôt que sur l'école.
            $table->foreignId('trajet_id')->constrained('bus_trajets')->cascadeOnDelete();
            $table->string('nom');
            $table->unsignedSmallInteger('ordre')->default(1);
            $table->time('heure_passage')->nullable();
            $table->timestamps();

            $table->index(['trajet_id', 'ordre']);
        });

        Schema::create('bus_affectations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->foreignId('trajet_id')->constrained('bus_trajets')->cascadeOnDelete();
            $table->foreignId('arret_id')->nullable()->constrained('bus_arrets')->nullOnDelete();
            $table->foreignId('annee_scolaire_id')->nullable()->constrained('annee_scolaires')->nullOnDelete();
            $table->unsignedInteger('tarif_mensuel')->nullable();
            $table->enum('statut', ['actif', 'suspendu'])->default('actif');
            $table->timestamps();

            $table->index(['eleve_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bus_affectations');
        Schema::dropIfExists('bus_arrets');
        Schema::dropIfExists('bus_trajets');
        Schema::dropIfExists('bus_vehicules');
    }
};
