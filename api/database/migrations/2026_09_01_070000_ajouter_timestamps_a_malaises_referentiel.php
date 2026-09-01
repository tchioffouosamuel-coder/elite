<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Même défaut que `fonction_referentiel` (cf. migration
 * `2026_08_31_130000_ajouter_timestamps_a_fonction_referentiel`) : créée sans
 * `created_at`/`updated_at`, cette table ne peut pas entrer dans le registre
 * de synchronisation, qui trie/filtre toute entité sur `updated_at`. Contrairement
 * à ce cas précédent, le backfill est fait dans la même migration — inutile de
 * répéter l'aller-retour, `whereNull` sur des colonnes qui viennent d'être
 * ajoutées ne peut cibler que les lignes déjà existantes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('malaises_referentiel', function (Blueprint $table) {
            $table->timestamps();
        });

        DB::table('malaises_referentiel')->update(['created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('malaises_referentiel', function (Blueprint $table) {
            $table->dropTimestamps();
        });
    }
};
