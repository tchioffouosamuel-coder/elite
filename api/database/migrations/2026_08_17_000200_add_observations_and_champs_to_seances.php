<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ce que l'enseignant relève après coup : des observations libres, et les
     * valeurs des champs personnalisés définis pour la matière (projets de
     * groupe, points particuliers…). Une colonne JSON plutôt qu'une table de
     * valeurs : le nombre de champs reste modeste et n'a jamais besoin d'être
     * interrogé colonne par colonne.
     */
    public function up(): void
    {
        Schema::table('seances', function (Blueprint $table) {
            $table->text('observations')->nullable()->after('contenu');
            $table->json('donnees_personnalisees')->nullable()->after('observations');
        });
    }

    public function down(): void
    {
        Schema::table('seances', function (Blueprint $table) {
            $table->dropColumn(['observations', 'donnees_personnalisees']);
        });
    }
};
