<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rattache une dépense à un véhicule précis (maintenance, entretien,
     * carburant…) — nullable : la plupart des dépenses n'ont rien à voir
     * avec la flotte, et forcer le champ y ferait bruit sur tout le reste.
     */
    public function up(): void
    {
        Schema::table('depenses', function (Blueprint $table) {
            $table->foreignId('vehicule_id')->nullable()->after('compte_comptable_id')
                ->constrained('bus_vehicules')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('depenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vehicule_id');
        });
    }
};
