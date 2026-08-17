<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aligne le volet discipline sur le modèle _smapp : une sanction se
 * PRONONCE (elle a un statut, comme un conseil de discipline la valide ou la
 * classe sans suite), pas seulement se constate. On ajoute aussi les deux
 * degrés d'avertissement qui manquaient avant la corvée, et de quoi situer
 * une exclusion temporaire sur un calendrier plutôt que sur une simple durée.
 */
return new class extends Migration
{
    public function up(): void
    {
        // SQLite (tests) ne sait pas modifier un enum en place ; MySQL a besoin
        // du SQL brut pour ça (Doctrine DBAL ne gère pas non plus les enums).
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE sanctions MODIFY type ENUM('avertissement', 'blame', 'corvee', 'exclusion_temporaire', 'exclusion_definitive', 'autre') NOT NULL");
        }

        Schema::table('sanctions', function (Blueprint $table) {
            $table->enum('statut', ['en_attente', 'confirmee', 'annulee'])->default('en_attente')->after('date_sanction');
            $table->date('date_debut')->nullable()->after('duree_jours');
            $table->date('date_fin')->nullable()->after('date_debut');
            $table->text('commentaire')->nullable()->after('motif');
            // Une sanction confirmée peut compter comme note de conduite au
            // bulletin ; un simple avertissement verbal, en général, non.
            $table->boolean('impacte_bulletin')->default(false)->after('statut');
        });
    }

    public function down(): void
    {
        Schema::table('sanctions', function (Blueprint $table) {
            $table->dropColumn(['statut', 'date_debut', 'date_fin', 'commentaire', 'impacte_bulletin']);
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE sanctions MODIFY type ENUM('corvee', 'exclusion_temporaire', 'exclusion_definitive', 'autre') NOT NULL");
        }
    }
};
