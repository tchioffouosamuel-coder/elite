<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Une seule ligne par instance locale : le compte auquel ce poste est
     * lié (mono-utilisateur, pas de changement de compte — cf. le plan
     * desktop), les jetons servant à parler au serveur distant, et l'état
     * de la dernière synchronisation.
     */
    public function up(): void
    {
        Schema::create('desktop_provisioning', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->string('serveur_url');
            $table->text('token');
            $table->text('refresh_token');
            // Curseur de la dernière synchronisation `pull` réussie
            // ({@see \App\Http\Controllers\Api\V1\SyncController::pull()}) —
            // même format Zulu que celui déjà utilisé par le mobile.
            $table->string('curseur_sync')->nullable();
            $table->timestamp('dernier_pull_le')->nullable();
            $table->timestamp('dernier_push_le')->nullable();
            $table->timestamp('provisionne_le');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('desktop_provisioning');
    }
};
