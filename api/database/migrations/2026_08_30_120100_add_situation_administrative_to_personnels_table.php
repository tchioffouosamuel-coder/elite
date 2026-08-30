<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Situation administrative telle que reprise dans le tableau « Mise en
     * place du personnel » du rapport de rentrée MINEDUB : type de contrat,
     * statut (essai/permanent/vacataire), catégorie/échelon, grade, ainsi que
     * le suivi des absences au poste et des décès (tableaux 10-14 du canevas).
     */
    public function up(): void
    {
        Schema::table('personnels', function (Blueprint $table) {
            $table->enum('type_contrat', ['CDI', 'CDD'])->nullable()->after('statut');
            $table->enum('statut_contrat', ['essai', 'permanent', 'vacataire'])->nullable()->after('type_contrat');
            $table->string('categorie_echelon', 20)->nullable()->after('statut_contrat');
            // Grade MINEDUB : IPEG/IEG/IEMP/IAEG/IC/MP/MC pour le personnel
            // fonctionnaire, ou CAPIEMP/Licence/BAC/Probatoire/BEPC-CAP/CEPC/
            // Maitre des Parents/Maitre Communautaire pour le privé — laissé en
            // chaîne libre plutôt qu'en enum strict, la liste variant selon le
            // cycle (maternelle/primaire/secondaire) et le secteur de l'école.
            $table->string('grade_minedub', 50)->nullable()->after('categorie_echelon');
            $table->date('absent_depuis')->nullable()->after('grade_minedub');
            $table->string('motif_absence')->nullable()->after('absent_depuis');
            $table->boolean('dossier_disciplinaire')->default(false)->after('motif_absence');
            $table->date('date_deces')->nullable()->after('dossier_disciplinaire');
        });
    }

    public function down(): void
    {
        Schema::table('personnels', function (Blueprint $table) {
            $table->dropColumn([
                'type_contrat',
                'statut_contrat',
                'categorie_echelon',
                'grade_minedub',
                'absent_depuis',
                'motif_absence',
                'dossier_disciplinaire',
                'date_deces',
            ]);
        });
    }
};
