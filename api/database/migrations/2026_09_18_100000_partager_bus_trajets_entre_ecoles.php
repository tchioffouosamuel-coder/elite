<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Même traitement que 2026_09_13_140000_partager_bus_vehicules_entre_ecoles :
 * un trajet dessert souvent des élèves de plusieurs écoles du complexe sur le
 * même circuit (cf. BusTrajet::scopeForSchool, déjà un no-op) — le rattacher
 * à une seule école en écriture forçait un `school_id` que les comptes
 * multi-écoles n'ont aucune raison de choisir arbitrairement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bus_trajets', function (Blueprint $table) {
            $table->dropForeign(['school_id']);
            $table->foreignId('school_id')->nullable()->change();
        });

        DB::table('bus_trajets')->update(['school_id' => null]);
    }

    public function down(): void
    {
        throw new RuntimeException('La liaison école des trajets partagés ne peut pas être restaurée automatiquement.');
    }
};
