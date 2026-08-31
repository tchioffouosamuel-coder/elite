<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * File d'attente des écritures faites sur une instance locale (client
     * desktop) en attendant leur rejeu vers le serveur distant.
     *
     * `id` est un UUID généré côté client, pas un auto-incrément : c'est lui
     * qui sert de clé d'idempotence au rejeu ({@see \App\Console\Commands\SyncPush}),
     * exactement comme l'outbox mobile envoie déjà son propre identifiant
     * d'opération à {@see \App\Http\Controllers\Api\V1\SyncController::push()}.
     */
    public function up(): void
    {
        Schema::create('sync_outbox', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('methode', 10);
            $table->string('chemin');
            $table->json('corps')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('pushed_at')->nullable();
            $table->unsignedTinyInteger('tentatives')->default(0);

            $table->index('pushed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_outbox');
    }
};
