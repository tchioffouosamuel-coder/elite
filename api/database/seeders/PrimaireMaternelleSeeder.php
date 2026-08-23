<?php

namespace Database\Seeders;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Eleve;
use App\Models\FonctionReferentiel;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\NiveauScolaire;
use App\Models\Note;
use App\Models\Personnel;
use App\Models\School;
use App\Models\Sequence;
use App\Models\Setting;
use App\Models\Trimestre;
use App\Models\Tuteur;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Jeu de démonstration pour les deux écoles non secondaires du complexe.
 *
 * Reproduit l'organisation d'archange : au primaire des niveaux d'enseignement
 * pilotés par un animateur, une classe par niveau tenue par un unique
 * titulaire, et des matières notées sur un barème propre puis évaluées par
 * volets. La maternelle suit le même mode de notation mais ignore les niveaux :
 * ses trois sections sont directement des classes.
 */
class PrimaireMaternelleSeeder extends Seeder
{
    /** Barème et volet pratique repris du curriculum primaire camerounais. */
    private const MATIERES_PRIMAIRE = [
        ['Français', 'French', 'FRA', 50, false],
        ['Mathématiques', 'Mathematics', 'MAT', 50, false],
        ['Anglais', 'English', 'ANG', 30, false],
        ['Sciences et Technologie', 'Science and Technology', 'SCT', 30, false],
        ['Éducation à la Citoyenneté', 'Citizenship Education', 'ECM', 20, false],
        ['Technologies de l\'Information', 'ICT', 'TIC', 20, true],
        ['Éducation Artistique', 'Arts Education', 'ART', 20, true],
        ['Éducation Physique et Sportive', 'Physical Education', 'EPS', 20, true],
    ];

    private const MATIERES_MATERNELLE = [
        ['Langage et Communication', 'Language and Communication', 'LAN', 30, false],
        ['Graphisme et Écriture', 'Handwriting', 'GRA', 30, true],
        ['Pré-mathématiques', 'Pre-mathematics', 'PMA', 30, false],
        ['Éveil et Découverte', 'Discovery', 'EVE', 20, false],
        ['Motricité', 'Motor Skills', 'MOT', 20, true],
        ['Vie Pratique et Sociale', 'Practical Life', 'VPS', 20, true],
    ];

    private const NIVEAUX_PRIMAIRE = [
        ['SIL', 'Section d\'Initiation au Langage'],
        ['CP', 'Cours Préparatoire'],
        ['CE1', 'Cours Élémentaire 1'],
        ['CE2', 'Cours Élémentaire 2'],
        ['CM1', 'Cours Moyen 1'],
        ['CM2', 'Cours Moyen 2'],
    ];

    /** La maternelle n'a pas de degrés : ses sections sont directement des classes. */
    private const SECTIONS_MATERNELLE = [
        ['PS', 'Petite Section'],
        ['MS', 'Moyenne Section'],
        ['GS', 'Grande Section'],
    ];

    private const NOMS = [
        ['Ngo Bell', 'Adèle', 'F'], ['Fotso', 'Bertrand', 'M'], ['Manga', 'Chantal', 'F'],
        ['Etoundi', 'Danielle', 'F'], ['Kamdem', 'Emmanuel', 'M'], ['Nkodo', 'Franck', 'M'],
        ['Abena', 'Grâce', 'F'], ['Tchuente', 'Hervé', 'M'], ['Biya', 'Ingrid', 'F'],
        ['Owona', 'Junior', 'M'], ['Mballa', 'Karine', 'F'], ['Sadi', 'Landry', 'M'],
    ];

    private const ENSEIGNANTS = [
        ['Ateba', 'Solange', 'F'], ['Njoya', 'Paul', 'M'], ['Essomba', 'Marie', 'F'],
        ['Wandji', 'Thomas', 'M'], ['Ondoa', 'Bernadette', 'F'], ['Fouda', 'Célestin', 'M'],
        ['Mbarga', 'Justine', 'F'], ['Talla', 'Vincent', 'M'], ['Ngono', 'Pauline', 'F'],
    ];

    public function run(): void
    {
        foreach (['primaire', 'maternelle'] as $type) {
            $school = School::where('type', $type)->first();

            if (! $school) {
                $this->command?->warn("Aucune école de type {$type} — étape ignorée.");

                continue;
            }

            $this->seedEcole(
                $school,
                $type === 'primaire' ? self::NIVEAUX_PRIMAIRE : self::SECTIONS_MATERNELLE,
                $type === 'primaire' ? self::MATIERES_PRIMAIRE : self::MATIERES_MATERNELLE,
            );
        }
    }

    private function seedEcole(School $school, array $niveaux, array $matieres): void
    {
        // Archange évalue sur trois séquences par trimestre.
        Setting::set($school->id, 'num_sequences', 3);
        Setting::set($school->id, 'passage_moyenne_min', 10);

        $niveauReference = Niveau::where('code', strtoupper($school->type))->first();
        $annee = AnneeScolaire::where('school_id', $school->id)->where('is_active', true)->first()
            ?? AnneeScolaire::where('school_id', $school->id)->first();

        $trimestres = $this->seedTrimestres($annee);
        $personnels = $this->seedPersonnels($school);
        $matiereModels = $this->seedMatieres($school, $matieres);

        // La maternelle ne range pas ses classes par degré : ses trois sections
        // se suffisent à elles-mêmes, sans animateur de niveau au-dessus.
        $avecNiveaux = $school->type === 'primaire';

        foreach ($niveaux as $index => [$code, $libelle]) {
            $niveauScolaire = $avecNiveaux
                ? NiveauScolaire::firstOrCreate(
                    ['school_id' => $school->id, 'code' => $code],
                    [
                        'libelle' => $libelle,
                        'ordre' => $index + 1,
                        // Un animateur pour deux niveaux consécutifs, comme en pratique
                        // dans les petites structures.
                        'animateur_personnel_id' => $personnels[intdiv($index, 2) % count($personnels)]->id,
                    ],
                )
                : null;

            // Sans degré au-dessus d'elle, la classe de maternelle porte le nom
            // complet de sa section plutôt qu'un code suivi d'une lettre.
            $nomClasse = $avecNiveaux ? $code.' A' : $libelle;

            $classe = Classe::firstOrCreate(
                ['school_id' => $school->id, 'nom' => $nomClasse],
                [
                    'niveau_id' => $niveauReference?->id,
                    'niveau_scolaire_id' => $niveauScolaire?->id,
                    'titulaire_id' => $personnels[$index % count($personnels)]->id,
                    'capacite' => 40,
                ],
            );

            $classe->update([
                'niveau_scolaire_id' => $niveauScolaire?->id,
                'titulaire_id' => $personnels[$index % count($personnels)]->id,
            ]);

            $affectations = $this->affecterMatieres($classe, $matiereModels);
            $eleves = $this->seedEleves($school, $classe, $index);
            $this->seedNotes($eleves, $affectations, $trimestres->first());
        }

        $this->seedCompteTitulaire($school, $personnels->first(), $niveauReference?->id);
    }

    /**
     * Accès de connexion pour un titulaire, afin de pouvoir éprouver la saisie
     * des notes sous le rôle `enseignant` : au primaire c'est le titulaire qui
     * saisit toutes les matières de sa classe, règle qu'un compte
     * administrateur ne met pas à l'épreuve.
     */
    private function seedCompteTitulaire(School $school, Personnel $titulaire, ?int $niveauId): void
    {
        $email = 'titulaire.'.$school->type.'@elites-school.test';

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $titulaire->nom_complet,
                'password' => 'password',
                'school_id' => $school->id,
                'niveau_id' => $niveauId,
                'is_active' => true,
            ]
        );
        $user->syncRoles(['enseignant']);

        $titulaire->update(['user_id' => $user->id, 'email' => $email]);
    }

    /** @return Collection<int, Trimestre> */
    private function seedTrimestres(AnneeScolaire $annee): Collection
    {
        $definitions = [
            [1, 'Trimestre 1', '2026-09-01', '2026-12-19'],
            [2, 'Trimestre 2', '2027-01-05', '2027-03-27'],
            [3, 'Trimestre 3', '2027-04-06', '2027-06-30'],
        ];

        return collect($definitions)->map(function (array $d) use ($annee) {
            [$ordre, $libelle, $debut, $fin] = $d;

            $trimestre = Trimestre::firstOrCreate(
                ['annee_scolaire_id' => $annee->id, 'ordre' => $ordre],
                ['libelle' => $libelle, 'date_debut' => $debut, 'date_fin' => $fin, 'is_active' => $ordre === 1],
            );

            for ($i = 1; $i <= 3; $i++) {
                Sequence::firstOrCreate(
                    ['trimestre_id' => $trimestre->id, 'ordre' => $i],
                    ['libelle' => "Séquence {$i}"],
                );
            }

            return $trimestre;
        });
    }

    /** @return Collection<int, Personnel> */
    private function seedPersonnels(School $school): Collection
    {
        return collect(self::ENSEIGNANTS)->map(function (array $e, int $i) use ($school) {
            [$nom, $prenom] = $e;

            return Personnel::firstOrCreate(
                ['school_id' => $school->id, 'matricule' => sprintf('%s-ENS-%02d', strtoupper(substr($school->type, 0, 3)), $i + 1)],
                [
                    'nom_complet' => $prenom.' '.mb_strtoupper($nom),
                    'fonction_id' => $this->fonctionId($school, 'Enseignant', 'Teacher'),
                    'telephone' => '69'.str_pad((string) (1000000 + $i), 7, '0', STR_PAD_LEFT),
                    'statut' => 'actif',
                ],
            );
        });
    }

    private function fonctionId(School $school, string $labelFr, ?string $labelEn = null): int
    {
        return FonctionReferentiel::firstOrCreate(
            ['school_id' => $school->id, 'label_fr' => $labelFr],
            ['label_en' => $labelEn],
        )->id;
    }

    /** @return Collection<int, Matiere> */
    private function seedMatieres(School $school, array $matieres): Collection
    {
        return collect($matieres)->map(function (array $m) use ($school) {
            [$nom, $nomEn, $abbr, $bareme, $pratique] = $m;

            return Matiere::firstOrCreate(
                ['school_id' => $school->id, 'nom' => $nom],
                [
                    'nom_en' => $nomEn,
                    'abbreviation' => $abbr,
                    'notation' => $bareme,
                    'evalue_pratique' => $pratique,
                    'statut' => 'actif',
                ],
            );
        });
    }

    /** @return Collection<int, ClasseMatiere> */
    private function affecterMatieres(Classe $classe, Collection $matieres): Collection
    {
        return $matieres->map(fn (Matiere $matiere) => ClasseMatiere::firstOrCreate(
            ['classe_id' => $classe->id, 'matiere_id' => $matiere->id],
            [
                // Au primaire c'est le titulaire de la classe qui enseigne tout.
                'personnel_id' => $classe->titulaire_id,
                'coefficient' => 1,
                'statut' => 'actif',
            ],
        ));
    }

    /** @return Collection<int, Eleve> */
    private function seedEleves(School $school, Classe $classe, int $classeIndex): Collection
    {
        return collect(self::NOMS)->take(8)->map(function (array $n, int $i) use ($school, $classe, $classeIndex) {
            [$nom, $prenom, $sexe] = $n;
            $matricule = sprintf('%s-%02d%02d', strtoupper(substr($school->type, 0, 3)), $classeIndex + 1, $i + 1);

            $eleve = Eleve::firstOrCreate(
                ['school_id' => $school->id, 'matricule' => $matricule],
                [
                    'classe_id' => $classe->id,
                    'nom_complet' => $prenom.' '.mb_strtoupper($nom),
                    'sexe' => $sexe,
                    'date_naissance' => now()->subYears(6 + $classeIndex)->subDays($i * 37)->toDateString(),
                    'lieu_naissance' => 'Bertoua',
                    'statut' => 'actif',
                ],
            );

            // Sans tuteur, les listes d'élèves et les bulletins afficheraient un
            // tiret là où l'usage montre toujours un parent joignable.
            $tuteur = Tuteur::firstOrCreate(
                [
                    'school_id' => $school->id,
                    'telephone' => sprintf('6%02d%05d', $classeIndex + 70, $i + 1),
                ],
                ['nom_complet' => 'Parent de '.$prenom.' '.mb_strtoupper($nom)],
            );

            $eleve->tuteurs()->syncWithoutDetaching(
                [$tuteur->id => ['lien_parente' => 'parent', 'is_principal' => true]]
            );

            return $eleve;
        });
    }

    /**
     * Notes du premier trimestre : un volet × une séquence = une note, tirée
     * dans une plage proportionnelle au barème pour rester réaliste.
     */
    private function seedNotes(Collection $eleves, Collection $affectations, Trimestre $trimestre): void
    {
        $sequences = $trimestre->sequences()->orderBy('ordre')->get();

        foreach ($eleves as $eleve) {
            foreach ($affectations as $classeMatiere) {
                $matiere = $classeMatiere->matiere;
                $composantes = $matiere->composantes();
                // Le barème de la matière se répartit sur ses volets.
                $maxParVolet = $matiere->notation / count($composantes);

                foreach ($composantes as $composante) {
                    foreach ($sequences as $sequence) {
                        Note::firstOrCreate(
                            [
                                'eleve_id' => $eleve->id,
                                'classe_matiere_id' => $classeMatiere->id,
                                'sequence_id' => $sequence->id,
                                'composante' => $composante,
                            ],
                            ['valeur' => round(random_int((int) ($maxParVolet * 40), (int) ($maxParVolet * 100)) / 100, 2)],
                        );
                    }
                }
            }
        }
    }
}
