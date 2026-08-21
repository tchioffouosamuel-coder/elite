<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ouvre l'inventaire à la vente au comptoir.
 *
 * Deux notions manquaient, et elles sont distinctes : `valeur_unitaire` est ce
 * que l'article a coûté à l'établissement, `prix_vente` ce que la famille paie.
 * Les confondre ferait afficher une marge nulle sur toute la boutique.
 *
 * Un article n'est mis en vente que lorsqu'il porte un prix : pas de drapeau
 * « en vente » séparé, qui pourrait contredire un prix absent — les 40 tables
 * de la CM2-A restent de l'inventaire pur et ne remonteront jamais au comptoir.
 *
 * `code_barre` est unique sur toute la base et non par école : une étiquette
 * collée sur un article doit se résoudre sans ambiguïté quel que soit le
 * comptoir qui la scanne, y compris dans un complexe à plusieurs écoles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventaire_articles', function (Blueprint $table) {
            $table->string('code_barre', 32)->nullable()->unique()->after('nom');
            $table->unsignedInteger('prix_vente')->nullable()->after('valeur_unitaire');
        });
    }

    public function down(): void
    {
        Schema::table('inventaire_articles', function (Blueprint $table) {
            $table->dropUnique(['code_barre']);
            $table->dropColumn(['code_barre', 'prix_vente']);
        });
    }
};
