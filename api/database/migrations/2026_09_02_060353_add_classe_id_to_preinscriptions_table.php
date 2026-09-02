<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Classe choisie par l'admin pour la validation — distincte de
     * `donnees_eleve.classe_id` (la proposition du parent, jamais appliquée
     * telle quelle pour un élève déjà scolarisé, cf. le commentaire de
     * `note_admin` sur la table). `null` veut dire « ne pas changer la
     * classe actuelle » pour une réinscription, ou « suivre la proposition
     * du parent » pour une nouvelle inscription.
     */
    public function up(): void
    {
        Schema::table('preinscriptions', function (Blueprint $table) {
            $table->foreignId('classe_id')->nullable()->after('donnees_tuteurs')->constrained('classes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('preinscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('classe_id');
        });
    }
};
