<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ouvre la connexion par numéro de téléphone : un compte parent n'a pas
     * forcément d'adresse e-mail (cf. CompteParentService), et l'e-mail
     * n'est donc plus une exigence universelle. `phone` devient l'identifiant
     * unique côté parent, sur le même modèle que `email` côté personnel —
     * les deux colonnes coexistent, chaque famille de comptes n'en utilisant
     * qu'une.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->unique('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
