<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rattache les anciennes préinscriptions sans année à l'année scolaire
 * active de leur établissement.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('annee_scolaires')
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'school_id'])
            ->each(function (object $annee): void {
                DB::table('preinscriptions')
                    ->where('school_id', $annee->school_id)
                    ->whereNull('annee_scolaire_id')
                    ->update(['annee_scolaire_id' => $annee->id]);
            });
    }

    public function down(): void
    {
        // Le rattachement de données existantes ne peut pas être annulé
        // sans risquer d'effacer une année définie après cette migration.
    }
};
