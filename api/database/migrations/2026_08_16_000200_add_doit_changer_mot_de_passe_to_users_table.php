<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Renouvellement obligatoire du mot de passe à la première connexion.
     *
     * Les accès du personnel sont ouverts en masse avec un mot de passe commun
     * à tout l'établissement : tant que chaque agent ne l'a pas remplacé,
     * connaître ce mot de passe suffit à entrer sous l'identité de n'importe
     * lequel d'entre eux. Le drapeau ferme l'application à tout autre usage
     * que le changement lui-même.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('doit_changer_mot_de_passe')->default(false)->after('is_active');
        });

        // Les comptes d'agents déjà ouverts portent le mot de passe commun :
        // ils sont concernés au même titre que ceux à venir. Un compte sans
        // fiche de personnel (super administrateur, comptes de démonstration)
        // a été créé autrement et garde le sien.
        DB::table('users')
            ->whereIn('id', fn ($q) => $q->select('user_id')->from('personnels')->whereNotNull('user_id'))
            ->update(['doit_changer_mot_de_passe' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('doit_changer_mot_de_passe');
        });
    }
};
