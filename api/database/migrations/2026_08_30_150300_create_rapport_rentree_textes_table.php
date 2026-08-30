<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Blocs de texte libre du rapport de rentrée MINEDUB : sécurité (clôture,
     * détecteur de métaux, contrôle des armes blanches, surveillance des
     * pauses, autres mesures), gouvernements d'enfants, IRR, événements
     * socio-culturels, fêtes nationales, problèmes rencontrés, résolutions
     * des conseils des maîtres, doléances et conclusion générale. Une ligne
     * par rubrique plutôt qu'une colonne chacune : ce sont des rubriques du
     * canevas, pas des attributs structurés de l'école.
     */
    public function up(): void
    {
        Schema::create('rapport_rentree_textes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('annee_scolaire_id')->constrained('annee_scolaires')->cascadeOnDelete();
            $table->enum('rubrique', [
                'securite_cloture', 'securite_detecteur_metaux', 'securite_controle_armes',
                'securite_surveillance_pauses', 'securite_autres_mesures',
                'probleme_infrastructure_maternelle', 'doleances',
                'problemes_fonctionnement', 'resolutions_conseil_maitres',
                'gouvernements_enfants', 'irr', 'evenements_socioculturels',
                'fetes_nationales', 'conclusion_generale',
            ]);
            $table->text('contenu')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'annee_scolaire_id', 'rubrique'], 'rapport_rentree_textes_ecole_annee_rubrique_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapport_rentree_textes');
    }
};
