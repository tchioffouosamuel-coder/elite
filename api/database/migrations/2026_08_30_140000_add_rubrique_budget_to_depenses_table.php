<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rattache une dépense à une rubrique du budget de fonctionnement — les
     * cinq lignes du tableau 21 du rapport de rentrée MINEDUB (Primes de
     * rendement, Projet d'école, FENASSCO, Fonctionnement, Évaluation).
     * Distinct de `compte_comptable_id` : ce dernier sert la comptabilité
     * générale, la rubrique sert un canevas de reporting propre au MINEDUB —
     * une dépense peut porter les deux, indépendamment.
     */
    public function up(): void
    {
        Schema::table('depenses', function (Blueprint $table) {
            $table->enum('rubrique_budget_fonctionnement', [
                'primes_rendement', 'projet_ecole', 'fenassco', 'fonctionnement', 'evaluation',
            ])->nullable()->after('compte_comptable_id');
        });
    }

    public function down(): void
    {
        Schema::table('depenses', function (Blueprint $table) {
            $table->dropColumn('rubrique_budget_fonctionnement');
        });
    }
};
