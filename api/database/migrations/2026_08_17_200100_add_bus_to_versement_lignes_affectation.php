<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La souscription au bus se règle comme un frais annexe : un poste de plus
     * dans le même registre de versements, pas un système de paiement à part.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            // SQLite n'a pas de vrai type ENUM (c'est un CHECK) : rien à
            // modifier, la contrainte n'existe qu'en MySQL.
            return;
        }

        DB::statement(
            "ALTER TABLE versement_lignes MODIFY affectation ENUM('scolarite', 'frais_annexe', 'report_dette', 'bus') DEFAULT 'scolarite'"
        );
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement(
            "ALTER TABLE versement_lignes MODIFY affectation ENUM('scolarite', 'frais_annexe', 'report_dette') DEFAULT 'scolarite'"
        );
    }
};
