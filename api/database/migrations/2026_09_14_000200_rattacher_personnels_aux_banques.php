<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Fait passer la banque d'un agent d'un champ libre à un renvoi vers le
     * référentiel `banques` — même transformation que pour `fonction`
     * (cf. 2026_08_11_160100_rattacher_personnels_aux_fonctions.php). Les
     * libellés déjà saisis sont repris tels quels, par école et sans tenir
     * compte de la casse, plutôt que perdus au profit d'une liste « propre ».
     */
    public function up(): void
    {
        Schema::table('personnels', function (Blueprint $table) {
            $table->foreignId('banque_id')->nullable()->after('numero_compte')
                ->constrained('banques')->nullOnDelete();
        });

        $this->rattacherExistants();

        Schema::table('personnels', function (Blueprint $table) {
            $table->dropColumn('banque');
        });
    }

    private function rattacherExistants(): void
    {
        $libellesParEcole = DB::table('personnels')
            ->whereNotNull('banque')
            ->select('school_id', 'banque')
            ->distinct()
            ->get();

        $maintenant = now();

        foreach ($libellesParEcole as $ligne) {
            $libelle = trim((string) $ligne->banque);

            if ($libelle === '') {
                continue;
            }

            $banqueId = DB::table('banques')
                ->where('school_id', $ligne->school_id)
                ->whereRaw('LOWER(nom) = ?', [Str::lower($libelle)])
                ->value('id');

            if ($banqueId === null) {
                $banqueId = DB::table('banques')->insertGetId([
                    'school_id' => $ligne->school_id,
                    'nom' => $libelle,
                    'created_at' => $maintenant,
                    'updated_at' => $maintenant,
                ]);
            }

            DB::table('personnels')
                ->where('school_id', $ligne->school_id)
                ->whereRaw('LOWER(TRIM(banque)) = ?', [Str::lower($libelle)])
                ->update(['banque_id' => $banqueId]);
        }
    }

    public function down(): void
    {
        Schema::table('personnels', function (Blueprint $table) {
            $table->string('banque')->nullable()->after('numero_compte');
        });

        // Pas de `UPDATE ... JOIN` : cette syntaxe MySQL n'existe pas sous
        // SQLite (client desktop offline). Une sous-requête corrélée reste
        // portable sur les deux moteurs.
        DB::table('personnels')
            ->whereNotNull('banque_id')
            ->update([
                'banque' => DB::table('banques')
                    ->whereColumn('banques.id', 'personnels.banque_id')
                    ->select('nom'),
            ]);

        Schema::table('personnels', function (Blueprint $table) {
            $table->dropForeign(['banque_id']);
            $table->dropColumn('banque_id');
        });
    }
};
