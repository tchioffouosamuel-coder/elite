<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ce que le chauffeur fait de son itinéraire, jour après jour.
 *
 * `bus_ramassages` : le pointage d'un enfant effectivement monté à son arrêt.
 * Une ligne par enfant, par jour et par sens — l'absence de ligne vaut « pas
 * encore pris », et décocher supprime la ligne : c'est une case à cocher de
 * tournée, pas un registre d'absences (celui-là vit dans `presences`).
 *
 * `bus_remplacements` : un chauffeur empêché (ou la direction pour lui) rend
 * son itinéraire disponible sur une période ; un collègue le reprend. Tant
 * que `chauffeur_remplacant_id` est nul, l'itinéraire est offert sans
 * preneur — c'est ce que voit le tableau de bord des autres chauffeurs.
 * Rattaché au véhicule et non au trajet : l'itinéraire d'un chauffeur, c'est
 * le circuit de son bus, trajets et arrêts compris (cf. BusTrajet::vehicule).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bus_ramassages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bus_affectation_id')->constrained('bus_affectations')->cascadeOnDelete();
            // Arrêt où l'enfant a été pris, figé au pointage : sa souscription
            // peut changer d'arrêt plus tard sans réécrire les tournées déjà
            // faites.
            $table->foreignId('arret_id')->nullable()->constrained('bus_arrets')->nullOnDelete();
            $table->date('date_ramassage');
            $table->enum('sens', ['aller', 'retour'])->default('aller');
            $table->timestamp('pris_le');
            $table->foreignId('pointe_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Un enfant n'est pris qu'une fois par tournée : la contrainte
            // rend le double pointage (double tap, réseau qui rejoue la
            // requête) inoffensif plutôt que dupliqué.
            $table->unique(['bus_affectation_id', 'date_ramassage', 'sens'], 'bus_ramassages_tournee_unique');
            $table->index(['date_ramassage', 'sens']);
        });

        Schema::create('bus_remplacements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicule_id')->constrained('bus_vehicules')->cascadeOnDelete();
            $table->foreignId('chauffeur_titulaire_id')->nullable()->constrained('personnels')->nullOnDelete();
            $table->foreignId('chauffeur_remplacant_id')->nullable()->constrained('personnels')->nullOnDelete();
            $table->date('du');
            $table->date('au');
            $table->string('motif')->nullable();
            $table->enum('statut', ['disponible', 'pourvu', 'annule'])->default('disponible');
            $table->foreignId('ouvert_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('pourvu_le')->nullable();
            $table->timestamp('annule_le')->nullable();
            $table->timestamps();

            $table->index(['vehicule_id', 'du', 'au']);
            $table->index(['statut', 'du']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bus_remplacements');
        Schema::dropIfExists('bus_ramassages');
    }
};
