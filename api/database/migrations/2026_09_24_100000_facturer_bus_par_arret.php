<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Le transport se facture désormais à l'arrêt et non plus au trajet : deux
     * enfants d'un même circuit ne font pas la même distance. Les arrêts
     * existants reprennent la grille de leur trajet pour que rien ne change
     * sur les souscriptions en cours tant qu'aucun prix n'est retouché.
     *
     * La remise mensuelle accordée à la souscription (fratrie, personnel…)
     * se fige sur l'affectation, à côté du tarif, plutôt que d'être ressaisie
     * à chaque encaissement.
     */
    public function up(): void
    {
        Schema::table('bus_arrets', function (Blueprint $table) {
            $table->unsignedInteger('tarif_aller_simple')->nullable()->after('heure_passage');
            $table->unsignedInteger('tarif_retour_simple')->nullable()->after('tarif_aller_simple');
            $table->unsignedInteger('tarif_aller_retour')->nullable()->after('tarif_retour_simple');
        });

        Schema::table('bus_affectations', function (Blueprint $table) {
            $table->unsignedInteger('remise')->default(0)->after('tarif_mensuel');
        });

        DB::table('bus_trajets')->orderBy('id')->each(function (object $trajet) {
            DB::table('bus_arrets')->where('trajet_id', $trajet->id)->update([
                'tarif_aller_simple' => $trajet->tarif_aller_simple,
                'tarif_retour_simple' => $trajet->tarif_retour_simple,
                'tarif_aller_retour' => $trajet->tarif_aller_retour,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('bus_affectations', function (Blueprint $table) {
            $table->dropColumn('remise');
        });

        Schema::table('bus_arrets', function (Blueprint $table) {
            $table->dropColumn(['tarif_aller_simple', 'tarif_retour_simple', 'tarif_aller_retour']);
        });
    }
};
