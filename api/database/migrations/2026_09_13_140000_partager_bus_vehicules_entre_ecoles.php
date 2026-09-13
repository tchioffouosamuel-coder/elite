<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bus_vehicules', function (Blueprint $table) {
            $table->dropForeign(['school_id']);
            $table->dropUnique(['school_id', 'immatriculation']);
            $table->foreignId('school_id')->nullable()->change();
        });

        DB::table('bus_vehicules')->update(['school_id' => null]);
    }

    public function down(): void
    {
        throw new RuntimeException('La liaison école des véhicules partagés ne peut pas être restaurée automatiquement.');
    }
};
