<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un élève admis d'une classe sans classe supérieure (Tle, dernière classe
 * du cycle) doit se distinguer d'un départ ordinaire (`parti`) ou d'une
 * exclusion — cf. ConseilClasseService::valider().
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE eleves MODIFY statut ENUM('actif', 'parti', 'exclu', 'diplome') NOT NULL DEFAULT 'actif'");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE eleves MODIFY statut ENUM('actif', 'parti', 'exclu') NOT NULL DEFAULT 'actif'");
        }
    }
};
