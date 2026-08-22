<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Échéancier de la scolarité : le découpage en tranches et leurs dates.
 *
 * Jusqu'ici les frais formaient un montant annuel unique, et l'insolvabilité se
 * jugeait sur ce total : une famille à jour de sa première tranche était
 * comptée avec celle qui n'avait rien versé. La tranche apporte la seule chose
 * qui manquait — une date d'exigibilité — et permet de dire ce qui est dû
 * AUJOURD'HUI plutôt que sur l'année.
 *
 * Le pourcentage plutôt qu'un montant : les deux écoles n'ont pas la même
 * scolarité (90 000 et 110 000 F), une remise accordée à une famille change son
 * total, et un reliquat s'ajoute au dossier. Un échéancier en pourcentage suit
 * ces variations sans ressaisie ; la dernière tranche absorbe l'arrondi pour
 * que la somme retombe exactement sur le dû.
 *
 * Un établissement qui ne définit aucune tranche garde le comportement
 * antérieur — tout est exigible immédiatement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tranches_scolarite', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('annee_scolaire_id')->constrained('annee_scolaires')->cascadeOnDelete();

            $table->string('libelle');

            // Part du total dû couverte par la tranche. La somme des tranches
            // d'une même année doit valoir 100 — l'API le vérifie à l'écriture.
            $table->decimal('pourcentage', 5, 2);

            $table->date('date_echeance');

            // Rang dans l'année : c'est lui qui décide de l'ordre d'imputation
            // des versements, du plus ancien au plus récent.
            $table->unsignedTinyInteger('ordre');
            $table->timestamps();

            $table->unique(['school_id', 'annee_scolaire_id', 'ordre'], 'tranches_scolarite_unique');
            $table->index(['school_id', 'date_echeance']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tranches_scolarite');
    }
};
