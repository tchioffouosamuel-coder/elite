<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * École dans laquelle l'opération d'origine a été faite localement.
     *
     * Sans elle, `SyncController::rejouer()` n'a d'autre choix que de
     * dériver le `X-School-Id` du rejeu depuis le contexte de la requête
     * `/api/v1/sync` elle-même (`enTetesServeur()`) — qui n'a pourtant
     * aucun rapport avec l'école visée par CETTE opération précise. Pour un
     * compte non borné à une seule école (super admin d'un complexe), ce
     * contexte englobant retombe en mode agrégé et fixe arbitrairement une
     * seule école : une opération faite sur une AUTRE école du complexe
     * échoue alors avec un « introuvable » trompeur, la ligne visée existant
     * pourtant bien côté serveur — juste hors du périmètre imposé au rejeu.
     */
    public function up(): void
    {
        Schema::table('sync_outbox', function (Blueprint $table) {
            $table->unsignedBigInteger('school_id')->nullable()->after('chemin');
        });
    }

    public function down(): void
    {
        Schema::table('sync_outbox', function (Blueprint $table) {
            $table->dropColumn('school_id');
        });
    }
};
