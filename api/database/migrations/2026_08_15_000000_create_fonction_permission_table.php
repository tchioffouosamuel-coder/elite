<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Groupe de privilèges porté par une fonction du référentiel.
     *
     * Jusqu'ici les droits d'un agent venaient du rôle attribué à la main lors
     * de la création de son compte : deux enseignants pouvaient se retrouver
     * avec des droits différents, et retirer un droit à toute une catégorie
     * imposait de reprendre chaque compte. En rattachant les privilèges à la
     * fonction, tout agent qui la porte en hérite, et le super administrateur
     * modifie une fois pour tous.
     *
     * Les droits restent cumulatifs avec ceux du rôle : personne ne perd un
     * accès en migrant, et le rôle continue de porter les cas particuliers.
     */
    public function up(): void
    {
        Schema::create('fonction_permission', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fonction_referentiel_id')->constrained('fonction_referentiel')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->unique(['fonction_referentiel_id', 'permission_id'], 'fonction_permission_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fonction_permission');
    }
};
