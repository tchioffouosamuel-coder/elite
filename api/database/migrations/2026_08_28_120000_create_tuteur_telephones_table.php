<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tuteur_telephones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tuteur_id')->constrained('tuteurs')->cascadeOnDelete();
            $table->string('numero', 30);
            $table->boolean('is_principal')->default(false);
            $table->timestamps();

            $table->index('tuteur_id');
        });

        // Reprend le numéro déjà saisi de chaque tuteur existant comme son
        // numéro principal, pour que la nouvelle table parte alimentée plutôt
        // que vide face à des fiches créées avant ce changement.
        DB::table('tuteurs')
            ->whereNotNull('telephone')
            ->where('telephone', '!=', '')
            ->orderBy('id')
            ->get()
            ->each(function ($tuteur) {
                DB::table('tuteur_telephones')->insert([
                    'tuteur_id' => $tuteur->id,
                    'numero' => $tuteur->telephone,
                    'is_principal' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('tuteur_telephones');
    }
};
