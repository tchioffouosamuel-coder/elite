<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L'échéancier n'est plus forcément uniforme dès l'accord : l'employé choisit
 * la mensualité qu'il rembourse (au lieu qu'elle se déduise d'un nombre de
 * mois imposé) et le mois à partir duquel la retenue commence — une avance
 * accordée en cours de mois n'entame pas forcément la paie du même mois.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('avances_salaire', function (Blueprint $table) {
            $table->date('mois_debut_remboursement')->nullable()->after('mensualite');
        });

        Schema::table('demandes_avance_salaire', function (Blueprint $table) {
            $table->unsignedInteger('mensualite')->nullable()->after('nombre_mois');
            $table->date('mois_debut_remboursement')->nullable()->after('mensualite');
        });
    }

    public function down(): void
    {
        Schema::table('avances_salaire', function (Blueprint $table) {
            $table->dropColumn('mois_debut_remboursement');
        });

        Schema::table('demandes_avance_salaire', function (Blueprint $table) {
            $table->dropColumn(['mensualite', 'mois_debut_remboursement']);
        });
    }
};
