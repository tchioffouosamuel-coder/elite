<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Écoles supplémentaires d'un compte, en plus de son école principale
     * (`users.school_id`, conservée — elle pilote l'email générique, le mot
     * de passe par défaut, la fiche `Personnel`). Un compte multi-écoles
     * (ex. « Directrice Primaire et Maternelle ») en ajoute ici une ou
     * plusieurs, lues par {@see \App\Models\User::ecolesAccessibles()}.
     */
    public function up(): void
    {
        Schema::create('school_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'school_id']);
        });

        // Reprend l'existant : l'école principale de chaque compte devient
        // aussi sa première ligne dans la table pivot, pour que
        // `ecolesAccessibles()` n'ait qu'une seule source à lire.
        $lignes = DB::table('users')
            ->whereNotNull('school_id')
            ->get(['id', 'school_id'])
            ->map(fn ($u) => [
                'user_id' => $u->id,
                'school_id' => $u->school_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        foreach ($lignes->chunk(500) as $lot) {
            DB::table('school_user')->insert($lot->all());
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('school_user');
    }
};
