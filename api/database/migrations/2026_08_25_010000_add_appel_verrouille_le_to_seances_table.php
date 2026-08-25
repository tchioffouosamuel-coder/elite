<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seances', function (Blueprint $table) {
            // Horodatage du premier enregistrement de l'appel — fige le point
            // de départ de la fenêtre de correction de 15 minutes, plutôt que
            // `updated_at` qui se déplacerait à chaque correction et rouvrirait
            // indéfiniment la fenêtre.
            $table->timestamp('appel_verrouille_le')->nullable()->after('statut');
        });
    }

    public function down(): void
    {
        Schema::table('seances', function (Blueprint $table) {
            $table->dropColumn('appel_verrouille_le');
        });
    }
};
