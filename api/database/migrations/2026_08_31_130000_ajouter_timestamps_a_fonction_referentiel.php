<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `fonction_referentiel` a été créée sans `created_at`/`updated_at`
 * (référentiel jugé statique à l'époque). Le registre de synchronisation
 * (`RegistreSync`/`SyncController::lot()`) trie et filtre TOUTES ses entités
 * sur `updated_at` sans exception : demander cette table plantait la
 * synchronisation entière (`SQLSTATE... colonne "updated_at" introuvable`)
 * dès qu'un client desktop/mobile la réclamait.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fonction_referentiel', function (Blueprint $table) {
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('fonction_referentiel', function (Blueprint $table) {
            $table->dropTimestamps();
        });
    }
};
