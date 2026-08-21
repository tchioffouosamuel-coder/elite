<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cours en tronc commun : plusieurs classes réunies au même moment, devant le
 * même enseignant.
 *
 * Au secondaire technique, un enseignement se donne rarement classe par
 * classe : le tableau de service porte des lignes comme « ACT F3-ACC F3-
 * Marketing F3-Home eco F3 » — quatre classes, un cours, un professeur. Sans
 * ce regroupement, il fallait saisir quatre créneaux et faire quatre appels
 * pour un seul cours réel.
 *
 * Le créneau garde sa `classe_id` comme classe porteuse ; cette table liste
 * les classes qui la rejoignent. **Le tronc commun n'a pas de drapeau** : il
 * se déduit de la présence de lignes ici. Un booléen séparé finirait par
 * contredire les données — « tronc commun coché, aucune classe associée » est
 * un état qu'on ne peut pas atteindre s'il n'existe pas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emploi_du_temps_classe', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emploi_du_temps_id')->constrained('emplois_du_temps')->cascadeOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->timestamps();

            // Une classe ne rejoint un créneau qu'une fois.
            $table->unique(['emploi_du_temps_id', 'classe_id'], 'edt_classe_unique');
            $table->index('classe_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emploi_du_temps_classe');
    }
};
