<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('moratoires', function (Blueprint $table) {
            // Marque qu'une alerte d'expiration a déjà été envoyée aux parents pour
            // ce moratoire — rend la commande d'alerte idempotente si elle tourne
            // plusieurs fois le jour de l'expiration.
            $table->date('notifie_expiration_le')->nullable()->after('date_expiration');
        });
    }

    public function down(): void
    {
        Schema::table('moratoires', function (Blueprint $table) {
            $table->dropColumn('notifie_expiration_le');
        });
    }
};
