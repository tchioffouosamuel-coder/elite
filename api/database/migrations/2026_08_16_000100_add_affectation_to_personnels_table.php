<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Affectation telle que l'écrit le tableau de mise en place.
     *
     * La colonne « Affectations / Duty post » ne désigne pas toujours une
     * classe : à côté de « Nursery 1-A » et « CE1-B » on y lit « Bus driver »,
     * « Nurse / Infirmier » ou « Assistante GS ». Rattacher l'agent au
     * titulaire d'une classe couvre donc une partie des cas seulement — et le
     * reste, qui dit quand même où travaille l'agent, se perdait.
     *
     * On conserve le libellé brut ici, et le rattachement à la classe reste
     * fait en plus quand le libellé en désigne une.
     */
    public function up(): void
    {
        Schema::table('personnels', function (Blueprint $table) {
            $table->string('affectation')->nullable()->after('fonction_id');
        });
    }

    public function down(): void
    {
        Schema::table('personnels', function (Blueprint $table) {
            $table->dropColumn('affectation');
        });
    }
};
