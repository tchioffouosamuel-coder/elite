<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications_internes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Catégorise l'alerte pour un futur filtrage ou une icône dédiée côté
            // interface ; reste une chaîne libre plutôt qu'un enum pour ne pas
            // devoir migrer la table à chaque nouveau type de notification.
            $table->string('type');
            $table->string('titre');
            $table->text('message');
            // Lien relatif interne (ex: /eleves/12) vers lequel la notification
            // renvoie au clic ; nul quand elle n'est qu'informative.
            $table->string('lien')->nullable();
            $table->boolean('lu')->default(false);
            $table->timestamp('lu_le')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'lu', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications_internes');
    }
};
