<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /** Fonctions de départ, celles que le complexe emploie déjà. */
    private const CATALOGUE = [
        ['enseignant', 'Enseignant', 'Teacher', 'enseignement', 1],
        ['principal', 'Principal', 'Principal', 'direction', 2],
        ['directeur', 'Directeur', 'Headmaster', 'direction', 3],
        ['censeur', 'Censeur', 'Vice-Principal', 'direction', 4],
        ['surveillant_general', 'Surveillant Général', 'Discipline Master', 'direction', 5],
        ['conseiller_orientation', "Conseiller d'orientation", 'Guidance Counsellor', 'direction', 6],
        ['econome', 'Économe', 'Bursar', 'administration', 7],
        ['secretaire', 'Secrétaire', 'Secretary', 'administration', 8],
        ['documentaliste', 'Documentaliste', 'Librarian', 'administration', 9],
        ['infirmier', 'Infirmier', 'Nurse', 'appui', 10],
        ['gardien', 'Gardien', 'Security Guard', 'appui', 11],
        ['agent_entretien', "Agent d'entretien", 'Cleaner', 'appui', 12],
    ];

    /**
     * Rattache chaque fiche personnel au référentiel.
     *
     * Les libellés déjà saisis sont repris tels quels : ceux qui ne figurent pas
     * au catalogue rejoignent le référentiel plutôt que d'être écartés — perdre
     * la fonction d'un agent au profit d'une liste « propre » serait un mauvais
     * échange.
     */
    public function up(): void
    {
        $maintenant = now();

        foreach (self::CATALOGUE as [$code, $libelle, $libelleEn, $categorie, $ordre]) {
            DB::table('fonctions')->updateOrInsert(
                ['code' => $code],
                [
                    'libelle' => $libelle,
                    'libelle_en' => $libelleEn,
                    'categorie' => $categorie,
                    'ordre' => $ordre,
                    'is_active' => true,
                    'updated_at' => $maintenant,
                    'created_at' => $maintenant,
                ]
            );
        }

        Schema::table('personnels', function (Blueprint $table) {
            $table->foreignId('fonction_id')->nullable()->after('departement_id')
                ->constrained('fonctions')->nullOnDelete();
        });

        $this->rattacherExistants($maintenant);

        Schema::table('personnels', function (Blueprint $table) {
            $table->dropColumn('fonction');
        });
    }

    private function rattacherExistants(\Illuminate\Support\Carbon $maintenant): void
    {
        $parLibelle = DB::table('fonctions')->pluck('id', 'libelle')
            ->mapWithKeys(fn ($id, $libelle) => [Str::lower($libelle) => $id])
            ->all();

        $libellesEnPlace = DB::table('personnels')->whereNotNull('fonction')
            ->distinct()->pluck('fonction');

        foreach ($libellesEnPlace as $libelle) {
            $cle = Str::lower(trim((string) $libelle));

            if ($cle === '') {
                continue;
            }

            // Fonction employée mais absente du catalogue : on l'y ajoute, quitte
            // à ce que le super administrateur la range ou la désactive ensuite.
            if (! isset($parLibelle[$cle])) {
                $parLibelle[$cle] = DB::table('fonctions')->insertGetId([
                    'code' => Str::slug($libelle, '_'),
                    'libelle' => trim((string) $libelle),
                    'categorie' => 'enseignement',
                    'ordre' => 99,
                    'is_active' => true,
                    'created_at' => $maintenant,
                    'updated_at' => $maintenant,
                ]);
            }

            DB::table('personnels')
                ->whereRaw('LOWER(TRIM(fonction)) = ?', [$cle])
                ->update(['fonction_id' => $parLibelle[$cle]]);
        }
    }

    public function down(): void
    {
        Schema::table('personnels', function (Blueprint $table) {
            $table->string('fonction')->nullable()->after('departement_id');
        });

        DB::table('personnels')
            ->join('fonctions', 'fonctions.id', '=', 'personnels.fonction_id')
            ->update(['personnels.fonction' => DB::raw('fonctions.libelle')]);

        Schema::table('personnels', function (Blueprint $table) {
            $table->dropForeign(['fonction_id']);
            $table->dropColumn('fonction_id');
        });
    }
};
