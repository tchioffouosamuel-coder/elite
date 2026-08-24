<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La maternelle évalue par appréciation, sans barème ni volets à répartir
 * (cf. StoreCompetenceRequest::parAppreciation) : la validation le permet
 * déjà, mais la colonne posée par la migration d'origine restait NOT NULL,
 * ce qui faisait échouer en base toute compétence de maternelle créée sans
 * notation — une erreur 500 masquant une règle métier pourtant respectée
 * jusque-là par l'API.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competences', function (Blueprint $table) {
            $table->unsignedSmallInteger('notation')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        // Une compétence de maternelle a pu être enregistrée sans notation
        // entre-temps : on la ramène au défaut d'origine avant de réinterdire
        // NULL, sans quoi ces lignes bloqueraient le rollback.
        DB::table('competences')->whereNull('notation')->update(['notation' => 20]);

        Schema::table('competences', function (Blueprint $table) {
            $table->unsignedSmallInteger('notation')->default(20)->change();
        });
    }
};
