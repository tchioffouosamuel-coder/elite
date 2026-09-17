<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Étend la vacation horaire : un vacataire peut désormais relever de la CNPS
 * (déclaré au cas par cas, contrairement au salarié mensuel qui y est de
 * droit) et recevoir un complément ponctuel un mois donné, motivé par une
 * raison — un rattrapage, une prime exceptionnelle...
 *
 * `cnps_actif` est réglé sur le contrat (`remunerations`) : c'est une
 * déclaration qui vaut pour la suite, pas un choix mensuel. Il est aussi
 * recopié sur chaque bulletin au moment de la préparation, comme `bareme`
 * déjà présent sur la table : un bulletin remis à l'agent et déclaré à la
 * CNPS ne doit pas changer de statut rétroactivement si le contrat est
 * modifié ensuite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('remunerations', function (Blueprint $table) {
            $table->boolean('cnps_actif')->default(false)->after('taux_horaire');
        });

        Schema::table('bulletins_paie', function (Blueprint $table) {
            $table->boolean('cnps_actif')->default(false)->after('taux_horaire');
            $table->unsignedBigInteger('extra_montant')->default(0)->after('cnps_actif');
            $table->string('extra_motif')->nullable()->after('extra_montant');
        });
    }

    public function down(): void
    {
        Schema::table('bulletins_paie', function (Blueprint $table) {
            $table->dropColumn(['cnps_actif', 'extra_montant', 'extra_motif']);
        });
        Schema::table('remunerations', function (Blueprint $table) {
            $table->dropColumn('cnps_actif');
        });
    }
};
