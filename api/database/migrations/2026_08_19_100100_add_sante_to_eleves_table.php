<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fiche sanitaire de l'élève — un résumé permanent (groupe sanguin,
     * allergies, aptitude), à distinguer du journal des visites à
     * l'infirmerie (`visites_infirmerie`, événement par événement). C'est ce
     * résumé que le parent renseigne à la préinscription et consulte ensuite,
     * pas l'historique clinique détaillé qui reste réservé au personnel.
     */
    public function up(): void
    {
        Schema::table('eleves', function (Blueprint $table) {
            $table->string('groupe_sanguin')->nullable()->after('numero_acte_naissance');
            $table->text('situation_sanitaire')->nullable()->after('groupe_sanguin');
            $table->enum('aptitude', ['apte', 'inapte'])->default('apte')->after('situation_sanitaire');
            $table->text('allergies')->nullable()->after('aptitude');
            $table->string('photo_tenue_path')->nullable()->after('photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('eleves', function (Blueprint $table) {
            $table->dropColumn(['groupe_sanguin', 'situation_sanitaire', 'aptitude', 'allergies', 'photo_tenue_path']);
        });
    }
};
