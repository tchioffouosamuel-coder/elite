<?php

namespace Database\Seeders;

use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Departement;
use App\Models\FonctionReferentiel;
use App\Models\Personnel;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Reconstruit les fiches du personnel de démonstration, et rien d'autre.
 *
 * `DemoDataSeeder` et `PrimaireMaternelleSeeder` savent aussi les recréer,
 * mais ils reconstruisent au passage élèves, classes, matières et notes de
 * démonstration : les relancer sur une base qui porte des données réelles y
 * réinjecterait des élèves fictifs. Ce seeder n'écrit que dans `personnels`,
 * et rétablit les rattachements que la disparition des fiches avait mis à
 * NULL (chef de département, titulaire de classe, enseignant d'une matière).
 *
 * Idempotent : repéré par (école, matricule), il peut être relancé sans
 * créer de doublon.
 */
class RestaurerPersonnelSeeder extends Seeder
{
    /** Enseignants du secondaire — matricules TID-001…006. */
    private const ENSEIGNANTS_SECONDAIRE = [
        ['MELINGUI', 'Roger', 'Sciences'],
        ['ABENA', 'Clarisse', 'Lettres'],
        ['BIYA', 'Frank', 'Sciences'],
        ['NDONGO', 'Sylvie', 'Lettres'],
        ['ATANGANA', 'Hervé', 'Sciences'],
        ['MBALLA', 'Julienne', 'Lettres'],
    ];

    /** Encadrement du secondaire : matricule, nom, prénom, fonction, compte. */
    private const ENCADREMENT_SECONDAIRE = [
        ['TID-100', 'AKONO EVANG', 'Nathan', 'Surveillant Général', 'surveillant@elites-school.test'],
        ['TID-101', 'NGUEMA', 'Alice', 'Censeur', 'censeur@elites-school.test'],
        ['TID-102', 'USENI', 'Venyteh', 'Principal', 'principal@elites-school.test'],
    ];

    /** Enseignants du primaire et de la maternelle — matricules PRI-ENS-01, MAT-ENS-01… */
    private const ENSEIGNANTS_CYCLES = [
        ['Ateba', 'Solange'], ['Njoya', 'Paul'], ['Essomba', 'Marie'],
        ['Wandji', 'Thomas'], ['Ondoa', 'Bernadette'], ['Fouda', 'Célestin'],
        ['Mbarga', 'Justine'], ['Talla', 'Vincent'], ['Ngono', 'Pauline'],
    ];

    public function run(): void
    {
        $secondaire = School::where('type', 'secondaire')->first();

        if ($secondaire) {
            $this->restaurerSecondaire($secondaire);
        }

        foreach (['primaire', 'maternelle'] as $type) {
            $school = School::where('type', $type)->first();

            if ($school) {
                $this->restaurerCycle($school);
            }
        }

        $this->command?->info('Personnel restauré : '.Personnel::count().' fiche(s).');
    }

    private function restaurerSecondaire(School $school): void
    {
        $departements = collect(['Sciences', 'Lettres', 'Administration'])
            ->mapWithKeys(fn (string $nom) => [$nom => Departement::firstOrCreate(
                ['school_id' => $school->id, 'nom' => $nom],
            )]);

        $enseignants = collect(self::ENSEIGNANTS_SECONDAIRE)->map(fn (array $e, int $i) => $this->fiche(
            $school,
            sprintf('TID-%03d', $i + 1),
            $e[1].' '.$e[0],
            'Enseignant',
            $departements[$e[2]]->id,
            sprintf('enseignant%d@elites-school.test', $i + 1),
        ));

        foreach (self::ENCADREMENT_SECONDAIRE as [$matricule, $nom, $prenom, $fonction, $email]) {
            $this->fiche($school, $matricule, $prenom.' '.$nom, $fonction, $departements['Administration']->id, $email);
        }

        // Chefs de département : la FK avait été vidée avec les fiches.
        $departements['Sciences']->update(['head_personnel_id' => $enseignants[0]->id]);
        $departements['Lettres']->update(['head_personnel_id' => $enseignants[1]->id]);

        // Professeur principal de chaque classe, réparti comme le faisait
        // DemoDataSeeder — la FK avait elle aussi été vidée.
        Classe::forSchool($school->id)->orderBy('id')->get()->each(
            fn (Classe $classe, int $i) => $classe->update([
                'professeur_principal_id' => $enseignants[$i % $enseignants->count()]->id,
            ]),
        );

        // Au secondaire, chaque matière d'une classe a son enseignant ; on
        // répartit comme le faisait DemoDataSeeder, à l'index près.
        Classe::forSchool($school->id)->with('classeMatieres')->get()
            ->flatMap->classeMatieres
            ->values()
            ->each(fn (ClasseMatiere $cm, int $i) => $cm->update([
                'personnel_id' => $enseignants[$i % $enseignants->count()]->id,
            ]));
    }

    private function restaurerCycle(School $school): void
    {
        $prefixe = mb_strtoupper(mb_substr($school->type, 0, 3));

        $personnels = collect(self::ENSEIGNANTS_CYCLES)->map(fn (array $e, int $i) => $this->fiche(
            $school,
            sprintf('%s-ENS-%02d', $prefixe, $i + 1),
            $e[1].' '.mb_strtoupper($e[0]),
            'Enseignant',
            null,
            null,
            '69'.str_pad((string) (1000000 + $i), 7, '0', STR_PAD_LEFT),
        ));

        // Le titulaire du premier tour porte le compte de connexion du cycle.
        $titulaire = $personnels->first();
        $compte = User::where('email', 'titulaire.'.$school->type.'@elites-school.test')->first();

        if ($titulaire && $compte) {
            $titulaire->update(['user_id' => $compte->id, 'email' => $compte->email]);
        }

        // Au primaire et en maternelle, le titulaire tient toute la classe.
        Classe::forSchool($school->id)->orderBy('id')->get()->each(function (Classe $classe, int $i) use ($personnels) {
            $titulaire = $personnels[$i % $personnels->count()];

            $classe->update(['titulaire_id' => $titulaire->id]);
            $classe->classeMatieres()->update(['personnel_id' => $titulaire->id]);
        });
    }

    private function fiche(
        School $school,
        string $matricule,
        string $nomComplet,
        string $fonction,
        ?int $departementId = null,
        ?string $email = null,
        ?string $telephone = null,
    ): Personnel {
        $compte = $email ? User::where('email', $email)->first() : null;

        return Personnel::updateOrCreate(
            ['school_id' => $school->id, 'matricule' => $matricule],
            array_filter([
                'nom_complet' => $nomComplet,
                'fonction_id' => FonctionReferentiel::firstOrCreate(
                    ['school_id' => $school->id, 'label_fr' => $fonction],
                )->id,
                'departement_id' => $departementId,
                'telephone' => $telephone,
                'user_id' => $compte?->id,
                'email' => $compte?->email,
                'statut' => 'actif',
            ], fn ($valeur) => $valeur !== null),
        );
    }
}
