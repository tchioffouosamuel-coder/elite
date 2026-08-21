<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visites_infirmerie', function (Blueprint $table) {
            $table->enum('type_traitement', ['interne', 'externe', 'mixte'])->default('interne')->after('soins_prodiges');
            // Requise dès que la structure externe est impliquée (externe ou mixte) — validée côté FormRequest.
            $table->string('structure_externe')->nullable()->after('type_traitement');
            $table->string('autre_materiel')->nullable()->after('cout_soins');
            $table->unsignedInteger('cout_autre_materiel')->default(0)->after('autre_materiel');
            // cout_materiels : somme des lignes de visite_infirmerie_materiels ; cout_total :
            // cout_soins + cout_materiels + cout_autre_materiel. Dénormalisés (recalculés à
            // chaque sauvegarde côté contrôleur) pour que les stats/listes n'aient pas à
            // rejoindre la table des matériels à chaque affichage.
            $table->unsignedInteger('cout_materiels')->default(0)->after('cout_autre_materiel');
            $table->unsignedInteger('cout_total')->default(0)->after('cout_materiels');
        });
    }

    public function down(): void
    {
        Schema::table('visites_infirmerie', function (Blueprint $table) {
            $table->dropColumn(['type_traitement', 'structure_externe', 'autre_materiel', 'cout_autre_materiel', 'cout_materiels', 'cout_total']);
        });
    }
};
