<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            // Nullable + non unique : Orange peut renvoyer un DLR avant que
            // l'appelant n'ait eu le temps d'enregistrer l'identifiant côté
            // envoi, et certains fournisseurs recyclent des identifiants.
            $table->string('message_id')->nullable()->index();
            $table->string('recipient')->nullable();
            $table->string('status')->default('pending');
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_logs');
    }
};
