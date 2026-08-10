<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Au primaire et en maternelle, un unique enseignant (le « titulaire »)
     * tient la classe et enseigne toutes les matières — d'où un titulaire au
     * niveau de la classe plutôt qu'un enseignant par affectation
     * classe↔matière comme au secondaire.
     *
     * La classe est en outre rattachée à son niveau d'enseignement (SIL, CP…),
     * ce qui permet de regrouper plusieurs classes d'un même niveau sous un
     * même animateur.
     */
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->foreignId('niveau_scolaire_id')->nullable()->after('niveau_id')
                ->constrained('niveau_scolaires')->nullOnDelete();
            $table->foreignId('titulaire_id')->nullable()->after('professeur_principal_id')
                ->constrained('personnels')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropForeign(['niveau_scolaire_id']);
            $table->dropForeign(['titulaire_id']);
            $table->dropColumn(['niveau_scolaire_id', 'titulaire_id']);
        });
    }
};
