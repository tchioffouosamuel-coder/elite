<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Distingue « ce compte a été lié à ce poste » de « ce compte a
     * intégralement téléchargé ses données au moins une fois » — sans quoi
     * `DesktopProvisioningController::connexion()` (mot de passe local
     * valide dès la création de la ligne `desktop_provisioning`, cf.
     * `provisionner()`) laisserait entrer dans l'application un poste dont le
     * premier clonage a été interrompu (réseau coupé, application fermée en
     * plein milieu…) sans qu'aucune donnée n'y soit jamais arrivée.
     *
     * Portée par le compte, pas par chaque école (`desktop_provisioning_ecoles`) :
     * un compte à plusieurs écoles ne doit accéder à l'application qu'une
     * fois TOUTES répliquées, pas dès la première.
     */
    public function up(): void
    {
        Schema::table('desktop_provisioning', function (Blueprint $table) {
            $table->boolean('clonage_initial_complet')->default(false)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('desktop_provisioning', function (Blueprint $table) {
            $table->dropColumn('clonage_initial_complet');
        });
    }
};
