<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Le tarif dépend du trajet et de l'option choisie, pas de l'élève : il se
     * fixe une fois à la création du trajet plutôt que d'être ressaisi à
     * chaque souscription, où une erreur de frappe ferait payer deux enfants
     * du même trajet à des prix différents sans raison.
     */
    public function up(): void
    {
        Schema::table('bus_trajets', function (Blueprint $table) {
            $table->unsignedInteger('tarif_aller_simple')->nullable()->after('description');
            $table->unsignedInteger('tarif_retour_simple')->nullable()->after('tarif_aller_simple');
            $table->unsignedInteger('tarif_aller_retour')->nullable()->after('tarif_retour_simple');
        });
    }

    public function down(): void
    {
        Schema::table('bus_trajets', function (Blueprint $table) {
            $table->dropColumn(['tarif_aller_simple', 'tarif_retour_simple', 'tarif_aller_retour']);
        });
    }
};
