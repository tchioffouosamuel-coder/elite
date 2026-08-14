<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DEFAUTS = [
        ['Enseignant', 'Teacher'],
        ['Principal', 'Principal'],
        ['Directeur', 'Headmaster'],
        ['Censeur', 'Vice-Principal'],
        ['Surveillant Général', 'Discipline Master'],
        ["Conseiller d'orientation", 'Guidance Counsellor'],
        ['Économe', 'Bursar'],
        ['Secrétaire', 'Secretary'],
        ['Documentaliste', 'Librarian'],
        ['Infirmier', 'Nurse'],
        ['Gardien', 'Security Guard'],
        ["Agent d'entretien", 'Cleaner'],
    ];

    public function up(): void
    {
        Schema::create('fonction_referentiel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('label_fr');
            $table->string('label_en')->nullable();
            $table->unique(['school_id', 'label_fr']);
        });

        $this->alimenterReferentiel();

        if (! Schema::hasColumn('personnels', 'fonction_id')) {
            return;
        }

        try {
            Schema::table('personnels', function (Blueprint $table) {
                $table->dropForeign(['fonction_id']);
            });
        } catch (Throwable) {
            //
        }

        $this->rattacherPersonnels();

        Schema::table('personnels', function (Blueprint $table) {
            $table->foreign('fonction_id')->references('id')->on('fonction_referentiel')->nullOnDelete();
        });
    }

    private function alimenterReferentiel(): void
    {
        $anciennesFonctions = Schema::hasTable('fonctions')
            ? DB::table('fonctions')->orderBy('ordre')->get(['libelle', 'libelle_en'])
            : collect();

        $fonctions = $anciennesFonctions->isNotEmpty()
            ? $anciennesFonctions->map(fn ($f) => [(string) $f->libelle, $f->libelle_en])->all()
            : self::DEFAUTS;

        foreach (DB::table('schools')->pluck('id') as $schoolId) {
            foreach ($fonctions as [$labelFr, $labelEn]) {
                DB::table('fonction_referentiel')->updateOrInsert(
                    ['school_id' => $schoolId, 'label_fr' => $labelFr],
                    ['label_en' => $labelEn]
                );
            }
        }
    }

    private function rattacherPersonnels(): void
    {
        if (! Schema::hasTable('fonctions')) {
            return;
        }

        $anciennesParId = DB::table('fonctions')->pluck('libelle', 'id');

        DB::table('personnels')
            ->whereNotNull('fonction_id')
            ->orderBy('id')
            ->get(['id', 'school_id', 'fonction_id'])
            ->each(function ($personnel) use ($anciennesParId) {
                $label = $anciennesParId[$personnel->fonction_id] ?? null;

                if (! $label) {
                    DB::table('personnels')->where('id', $personnel->id)->update(['fonction_id' => null]);

                    return;
                }

                $nouvelleId = DB::table('fonction_referentiel')
                    ->where('school_id', $personnel->school_id)
                    ->where('label_fr', $label)
                    ->value('id');

                DB::table('personnels')->where('id', $personnel->id)->update(['fonction_id' => $nouvelleId]);
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('personnels', 'fonction_id')) {
            try {
                Schema::table('personnels', function (Blueprint $table) {
                    $table->dropForeign(['fonction_id']);
                });
            } catch (Throwable) {
                //
            }

            DB::table('personnels')->update(['fonction_id' => null]);

            if (Schema::hasTable('fonctions')) {
                Schema::table('personnels', function (Blueprint $table) {
                    $table->foreign('fonction_id')->references('id')->on('fonctions')->nullOnDelete();
                });
            }
        }

        Schema::dropIfExists('fonction_referentiel');
    }
};
