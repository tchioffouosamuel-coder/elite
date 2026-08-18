<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Appareils mobiles enregistrés pour les notifications push (FCM).
     *
     * Le jeton appartient à l'appareil, pas au compte : sur un téléphone
     * partagé entre deux surveillants, il change de propriétaire à chaque
     * connexion. D'où l'unicité sur le jeton seul — se réenregistrer
     * réattribue l'appareil au nouvel utilisateur au lieu de laisser
     * l'ancien continuer à recevoir ses notifications.
     */
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('jeton', 255)->unique();
            $table->enum('plateforme', ['android', 'ios']);
            // Sert à cibler `school-{id}` sans rejoindre la table users, et à
            // purger les appareils d'un établissement fermé.
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->timestamp('derniere_utilisation')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'plateforme']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
