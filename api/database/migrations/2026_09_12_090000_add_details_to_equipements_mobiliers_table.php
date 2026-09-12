<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipements_mobiliers', function (Blueprint $table) {
            $table->date('date_acquisition')->nullable()->after('nature');
            $table->decimal('prix_unitaire', 12, 2)->nullable()->after('besoin_quantite');
            $table->string('statut')->nullable()->after('prix_unitaire');
        });
    }

    public function down(): void
    {
        Schema::table('equipements_mobiliers', function (Blueprint $table) {
            $table->dropColumn(['date_acquisition', 'prix_unitaire', 'statut']);
        });
    }
};
