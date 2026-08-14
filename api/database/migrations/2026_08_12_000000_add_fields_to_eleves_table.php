<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eleves', function (Blueprint $table) {
            $table->string('numero_acte_naissance')->nullable()->after('nationalite');
            $table->enum('refugie', ['Oui', 'Non'])->nullable()->after('numero_acte_naissance');
            $table->enum('deplace_interne', ['Oui', 'Non'])->nullable()->after('refugie');
        });
    }

    public function down(): void
    {
        Schema::table('eleves', function (Blueprint $table) {
            $table->dropColumn(['numero_acte_naissance', 'refugie', 'deplace_interne']);
        });
    }
};
