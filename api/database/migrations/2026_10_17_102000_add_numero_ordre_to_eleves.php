<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Point 19 de la punch-list Elites-tech : les listes de classe doivent être
 * numérotées, l'enseignant pouvant fixer ou corriger le numéro à la main —
 * sans qu'il bouge tout seul à l'arrivée d'un nouvel élève (le nouvel élève
 * reçoit juste le prochain numéro libre, cf. `EleveService::create()`).
 *
 * Nullable : un élève sans classe (nouvelle fiche, transfert en cours) n'a
 * pas de numéro à porter. Le backfill ci-dessous numérote l'effectif
 * existant dans l'ordre alphabétique déjà affiché aujourd'hui, pour que
 * l'écran ne parte pas d'une liste à moitié vide de numéros.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eleves', function (Blueprint $table) {
            $table->unsignedInteger('numero_ordre')->nullable()->after('classe_id');
        });

        DB::table('eleves')
            ->whereNotNull('classe_id')
            ->orderBy('classe_id')
            ->orderBy('nom_complet')
            ->select('id', 'classe_id')
            ->get()
            ->groupBy('classe_id')
            ->each(function ($eleves) {
                $numero = 1;
                foreach ($eleves as $eleve) {
                    DB::table('eleves')->where('id', $eleve->id)->update(['numero_ordre' => $numero]);
                    $numero++;
                }
            });
    }

    public function down(): void
    {
        Schema::table('eleves', function (Blueprint $table) {
            $table->dropColumn('numero_ordre');
        });
    }
};
