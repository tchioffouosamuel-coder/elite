<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Motif d'absence relevé à l'appel.
     *
     * `justifie` disait seulement si l'absence était couverte ; le motif dit
     * pourquoi, ce qui change le traitement : une absence pour scolarité
     * (l'élève est retenu par l'établissement) ou une permission n'a pas la
     * même portée disciplinaire qu'une absence inconnue.
     */
    public function up(): void
    {
        Schema::table('presences', function (Blueprint $table) {
            $table->enum('motif', ['maladie', 'inconnu', 'scolarite', 'permission'])
                ->nullable()->after('statut');
        });
    }

    public function down(): void
    {
        Schema::table('presences', function (Blueprint $table) {
            $table->dropColumn('motif');
        });
    }
};
