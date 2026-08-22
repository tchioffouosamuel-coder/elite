<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un article d'inventaire peut être partagé par tout le complexe.
 *
 * `school_id` null signifie « toutes les écoles » : un seul article, un seul
 * stock, visible depuis les trois établissements qui y puisent ensemble. Les
 * articles existants gardent leur école — rien ne devient partagé sans qu'on
 * le demande.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventaire_articles', function (Blueprint $table) {
            $table->unsignedBigInteger('school_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Un article partagé n'a pas d'école à retrouver : le rattacher
        // arbitrairement à l'une des trois serait pire que de le supprimer,
        // car son stock serait alors compté pour elle seule.
        Schema::table('inventaire_articles', function (Blueprint $table) {
            $table->unsignedBigInteger('school_id')->nullable(false)->change();
        });
    }
};
