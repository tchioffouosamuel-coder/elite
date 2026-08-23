<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Une classe (6ème A, CM2 B…) désigne désormais un gabarit permanent de
 * l'établissement, pas une instance ouverte pour une année précise : elle
 * n'a plus besoin d'être recréée à chaque rentrée. L'année scolaire en
 * cours reste disponible ailleurs (AnneeScolaire::where('is_active', true))
 * pour tout ce qui doit encore s'y référer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropUnique(['school_id', 'annee_scolaire_id', 'nom']);
            $table->dropForeign(['annee_scolaire_id']);
            $table->dropColumn('annee_scolaire_id');
            $table->unique(['school_id', 'nom']);
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropUnique(['school_id', 'nom']);
            $table->foreignId('annee_scolaire_id')->nullable()->after('niveau_id')->constrained('annee_scolaires')->cascadeOnDelete();
            $table->unique(['school_id', 'annee_scolaire_id', 'nom']);
        });
    }
};
