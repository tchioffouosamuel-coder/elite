<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La migration précédente a ajouté `created_at`/`updated_at` en colonnes
 * nullables — Laravel ne les remplit jamais rétroactivement sur les lignes
 * déjà existantes. Résultat : `updated_at` reste NULL pour tout le
 * référentiel déjà en place, et `SyncController::lot()` filtre sur
 * `where('updated_at', '>', $depuis)` — une comparaison avec NULL n'est
 * jamais vraie en SQL. Un client desktop/mobile déjà synchronisé une
 * première fois (curseur non nul) ne recevrait donc JAMAIS ce référentiel,
 * quel que soit le nombre de synchronisations suivantes.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('fonction_referentiel')
            ->whereNull('updated_at')
            ->update(['created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Rien à défaire : un horodatage rétroactif n'est pas une donnée à retirer.
    }
};
