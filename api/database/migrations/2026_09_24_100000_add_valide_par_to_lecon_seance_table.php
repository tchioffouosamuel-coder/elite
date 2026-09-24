<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Auteur de la validation d'une leçon.
     *
     * La direction peut désormais cocher une leçon à la place de l'enseignant
     * (cf. MaJourneeService::peutIntervenir()) : sans cette colonne, rien ne
     * distinguerait une leçon déclarée par l'enseignant d'une leçon validée
     * par un administrateur. Nullable : les validations antérieures n'ont pas
     * d'auteur connu, et un compte supprimé ne doit pas effacer la leçon.
     */
    public function up(): void
    {
        Schema::table('lecon_seance', function (Blueprint $table) {
            $table->foreignId('valide_par')->nullable()->after('seance_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lecon_seance', function (Blueprint $table) {
            $table->dropConstrainedForeignId('valide_par');
        });
    }
};
