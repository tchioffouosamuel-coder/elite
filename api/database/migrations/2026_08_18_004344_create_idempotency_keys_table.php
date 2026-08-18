<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Réponses mémorisées des écritures portant un `Idempotency-Key`.
     *
     * Le mobile met ses écritures en file d'attente et les rejoue au retour du
     * réseau. Si une requête aboutit côté serveur mais que sa réponse se perd
     * (tunnel, coupure), le rejeu créerait un doublon — une seconde sanction,
     * un second versement. On renvoie ici la réponse d'origine à l'identique.
     */
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('cle', 100);
            // La clé n'est unique que par utilisateur : deux téléphones ne
            // doivent jamais pouvoir se voler mutuellement une réponse, même
            // en cas de collision d'UUID.
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Empreinte de la requête : une même clé rejouée sur une charge
            // utile différente est un bug client, pas un rejeu — on le refuse
            // plutôt que de renvoyer une réponse qui ne correspond pas.
            $table->string('empreinte', 64);
            $table->unsignedSmallInteger('statut_http');
            $table->longText('reponse');
            $table->timestamp('expire_le');
            $table->timestamps();

            $table->unique(['user_id', 'cle']);
            $table->index('expire_le');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
