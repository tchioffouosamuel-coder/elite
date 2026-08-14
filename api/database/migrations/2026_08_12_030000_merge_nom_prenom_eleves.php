<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fusionne `nom`/`prenom` en un unique `nom_complet` (texte libre) :
     * la séparation nom/prénom ne portait aucune règle métier, seulement un
     * hack de reconstruction fragile côté frontend (cf. EleveInscriptionPage).
     *
     * Backfill en PHP plutôt qu'un `CONCAT()` SQL : les tests tournent sur
     * SQLite (sans CONCAT natif), la prod sur MySQL — une boucle reste
     * portable dans les deux cas, et le volume par établissement est faible.
     */
    public function up(): void
    {
        Schema::table('eleves', function (Blueprint $table) {
            $table->string('nom_complet')->nullable()->after('matricule');
        });

        DB::table('eleves')->select('id', 'nom', 'prenom')->orderBy('id')->get()->each(
            fn ($row) => DB::table('eleves')->where('id', $row->id)
                ->update(['nom_complet' => trim($row->prenom.' '.$row->nom)])
        );

        Schema::table('eleves', function (Blueprint $table) {
            $table->string('nom_complet')->nullable(false)->change();
            $table->dropColumn(['nom', 'prenom']);
        });
    }

    /**
     * Retour arrière best-effort : `nom_complet` resplitté sur le premier
     * espace (prénom, puis reste = nom). Perd toute nuance d'un nom saisi
     * autrement (ordre inversé, particules) — assumé, comme pour les autres
     * migrations de fusion de ce projet (cf. rattacher_personnels_aux_fonctions).
     */
    public function down(): void
    {
        Schema::table('eleves', function (Blueprint $table) {
            $table->string('nom')->nullable()->after('matricule');
            $table->string('prenom')->nullable()->after('nom');
        });

        DB::table('eleves')->select('id', 'nom_complet')->orderBy('id')->get()->each(function ($row) {
            $parties = explode(' ', trim($row->nom_complet), 2);
            DB::table('eleves')->where('id', $row->id)->update([
                'prenom' => $parties[0] ?? '',
                'nom' => $parties[1] ?? '',
            ]);
        });

        Schema::table('eleves', function (Blueprint $table) {
            $table->string('nom')->nullable(false)->change();
            $table->string('prenom')->nullable(false)->change();
            $table->dropColumn('nom_complet');
        });
    }
};
