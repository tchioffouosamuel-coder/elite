<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marque les classes présentant un examen officiel. Dans _smapp, la colonne
 * `exam` de la table `classes` joue ce rôle : une valeur non vide désigne une
 * classe d'examen, et son contenu est le code imprimé sur les photos DECC/OBC
 * (BEPC, PROBATOIRE, BAC, CEP…).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->string('code_examen', 40)->nullable()->after('filiere');
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropColumn('code_examen');
        });
    }
};
