<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * État civil et données administratives du personnel.
     *
     * La fiche se limitait à ce qu'il faut pour ouvrir un compte (nom,
     * fonction, téléphone, e-mail). Le tableau de mise en place du personnel
     * tenu par l'établissement porte en réalité tout le dossier administratif :
     * identifiants CNI et CNPS, état civil, origine, diplômes. Sans ces
     * colonnes, chaque déclaration CNPS ou dossier ministériel obligeait à
     * revenir au classeur Excel.
     *
     * La paie (salaires, primes, IRPP, CNPS, bulletins) n'est volontairement
     * pas reprise ici : c'est un module à part, avec ses propres barèmes.
     */
    public function up(): void
    {
        Schema::table('personnels', function (Blueprint $table) {
            $table->string('civilite', 20)->nullable()->after('nom_complet');
            $table->enum('sexe', ['M', 'F'])->nullable()->after('civilite');
            $table->date('date_naissance')->nullable()->after('sexe');
            $table->string('numero_cni')->nullable()->after('date_naissance');
            $table->string('numero_cnps')->nullable()->after('numero_cni');
            $table->string('departement_origine')->nullable()->after('numero_cnps');
            $table->string('residence')->nullable()->after('departement_origine');
            $table->string('telephone_2')->nullable()->after('telephone');
            $table->enum('situation_matrimoniale', ['celibataire', 'marie', 'divorce', 'veuf'])->nullable()->after('telephone_2');
            $table->unsignedTinyInteger('nombre_enfants')->nullable()->after('situation_matrimoniale');
            $table->string('diplome_professionnel')->nullable()->after('nombre_enfants');
            $table->string('diplome_academique')->nullable()->after('diplome_professionnel');
            // `date_embauche` existe déjà et tient lieu de « Date Start ».
            $table->date('date_fin')->nullable()->after('date_embauche');
            $table->date('date_retraite')->nullable()->after('date_fin');
        });
    }

    public function down(): void
    {
        Schema::table('personnels', function (Blueprint $table) {
            $table->dropColumn([
                'civilite',
                'sexe',
                'date_naissance',
                'numero_cni',
                'numero_cnps',
                'departement_origine',
                'residence',
                'telephone_2',
                'situation_matrimoniale',
                'nombre_enfants',
                'diplome_professionnel',
                'diplome_academique',
                'date_fin',
                'date_retraite',
            ]);
        });
    }
};
