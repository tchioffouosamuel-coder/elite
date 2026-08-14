<?php

namespace Database\Seeders;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Complexe;
use App\Models\Departement;
use App\Models\Eleve;
use App\Models\FonctionReferentiel;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Note;
use App\Models\Personnel;
use App\Models\School;
use App\Models\Sequence;
use App\Models\Setting;
use App\Models\Trimestre;
use App\Models\Tuteur;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Jeu de données réaliste pour développer et démontrer le Palier A/B
 * sans attendre l'import des données réelles de la Fondation ELITES.
 */
class DemoDataSeeder extends Seeder
{
    use WithoutModelEvents;

    private const ENSEIGNANTS = [
        ['nom' => 'MELINGUI', 'prenom' => 'Roger', 'departement' => 'Sciences'],
        ['nom' => 'ABENA', 'prenom' => 'Clarisse', 'departement' => 'Lettres'],
        ['nom' => 'BIYA', 'prenom' => 'Frank', 'departement' => 'Sciences'],
        ['nom' => 'NDONGO', 'prenom' => 'Sylvie', 'departement' => 'Lettres'],
        ['nom' => 'ATANGANA', 'prenom' => 'Hervé', 'departement' => 'Sciences'],
        ['nom' => 'MBALLA', 'prenom' => 'Julienne', 'departement' => 'Lettres'],
    ];

    /** nom, abréviation, département, coefficient (uniforme sur les 4 classes pour la démo) */
    private const MATIERES = [
        ['nom' => 'Français', 'abbr' => 'FR', 'departement' => 'Lettres', 'coef' => 4],
        ['nom' => 'Mathématiques', 'abbr' => 'MATH', 'departement' => 'Sciences', 'coef' => 4],
        ['nom' => 'Anglais', 'abbr' => 'ANG', 'departement' => 'Lettres', 'coef' => 3],
        ['nom' => 'Physique-Chimie', 'abbr' => 'PC', 'departement' => 'Sciences', 'coef' => 2],
        ['nom' => 'Sciences de la Vie et de la Terre', 'abbr' => 'SVT', 'departement' => 'Sciences', 'coef' => 2],
        ['nom' => 'Histoire-Géographie', 'abbr' => 'HG', 'departement' => 'Lettres', 'coef' => 2],
        ['nom' => 'Éducation Physique et Sportive', 'abbr' => 'EPS', 'departement' => null, 'coef' => 1],
        ['nom' => 'Informatique', 'abbr' => 'INFO', 'departement' => 'Sciences', 'coef' => 1],
    ];

    private const ELEVES = [
        ['nom' => 'NGONO', 'prenom' => 'Aïcha', 'sexe' => 'F'],
        ['nom' => 'FOUDA', 'prenom' => 'Junior', 'sexe' => 'M'],
        ['nom' => 'TCHOUMI', 'prenom' => 'Grace', 'sexe' => 'F'],
        ['nom' => 'ESSOMBA', 'prenom' => 'Boris', 'sexe' => 'M'],
        ['nom' => 'MANGA', 'prenom' => 'Divine', 'sexe' => 'F'],
        ['nom' => 'ONANA', 'prenom' => 'Steve', 'sexe' => 'M'],
        ['nom' => 'BELLA', 'prenom' => 'Rosine', 'sexe' => 'F'],
        ['nom' => 'ETOUNDI', 'prenom' => 'Cabral', 'sexe' => 'M'],
        ['nom' => 'NKOMO', 'prenom' => 'Sandrine', 'sexe' => 'F'],
        ['nom' => 'MVONDO', 'prenom' => 'Yannick', 'sexe' => 'M'],
        ['nom' => 'BILOA', 'prenom' => 'Nadège', 'sexe' => 'F'],
        ['nom' => 'OWONA', 'prenom' => 'Landry', 'sexe' => 'M'],
        ['nom' => 'ABOMO', 'prenom' => 'Carelle', 'sexe' => 'F'],
        ['nom' => 'TSAFACK', 'prenom' => 'Enzo', 'sexe' => 'M'],
        ['nom' => 'DJOMO', 'prenom' => 'Marlène', 'sexe' => 'F'],
        ['nom' => 'KAMGA', 'prenom' => 'Aristide', 'sexe' => 'M'],
        ['nom' => 'FEUDJIO', 'prenom' => 'Odile', 'sexe' => 'F'],
        ['nom' => 'NANA', 'prenom' => 'Ismaël', 'sexe' => 'M'],
        ['nom' => 'SIMO', 'prenom' => 'Prisca', 'sexe' => 'F'],
        ['nom' => 'TCHUENTE', 'prenom' => 'Habib', 'sexe' => 'M'],
    ];

    public function run(): void
    {
        $niveauCollege = Niveau::where('code', 'COLLEGE')->firstOrFail();

        // ELITES est un complexe : une maternelle, un primaire et un secondaire,
        // chacun avec son propre mode de fonctionnement. Ce seeder crée les trois
        // écoles mais n'alimente que le secondaire ; PrimaireMaternelleSeeder
        // prend la suite sur les deux autres cycles.
        $complexe = Complexe::updateOrCreate(
            ['code' => 'ELITES'],
            [
                'name' => 'Complexe Scolaire ELITES',
                'address' => 'Bertoua-Monou2, Cameroun',
                'phone' => '698256973',
                'is_active' => true,
            ]
        );

        $school = School::updateOrCreate(
            ['code' => 'ELITES-BTA'],
            [
                'complexe_id' => $complexe->id,
                'type' => 'secondaire',
                'name' => 'Elites Bilingual Technical and Commercial College',
                'address' => 'Bertoua-Monou2, Cameroun',
                'phone' => '698256973',
                'is_active' => true,
            ]
        );

        $this->seedEcolesDuComplexe($complexe);

        // Le super administrateur voit les trois écoles et démarre sur le
        // secondaire ; le sélecteur de la barre supérieure permet de basculer.
        User::where('email', 'admin@elites-school.test')->update(['school_id' => $school->id]);

        // Seuils repérés dans le flux _smapp (settings.setting_key), configurables par école.
        foreach ([
            'num_sequences' => 2,
            'empty_cancel' => 'cancel',
            'honour_roll' => 14,
            'honour_attendance_max' => 20,
            'min_coef_per' => 50,
        ] as $key => $value) {
            Setting::set($school->id, $key, $value);
        }

        // `directeur@` est conservé : des notes de reprise et des scénarios de
        // test le référencent. `principal@` est le même rôle sous le nom que
        // l'établissement emploie dans ses propres documents (« The Principal »).
        $directeur = $this->compte($school, $niveauCollege->id, 'directeur@elites-school.test', 'TCHIOFFOUO Josué', 'admin_etablissement');
        $principal = $this->compte($school, $niveauCollege->id, 'principal@elites-school.test', 'USENI Venyteh', 'admin_etablissement');
        $censeur = $this->compte($school, $niveauCollege->id, 'censeur@elites-school.test', 'NGUEMA Alice', 'censeur_sg');
        $surveillant = $this->compte($school, $niveauCollege->id, 'surveillant@elites-school.test', 'AKONO EVANG Nathan', 'surveillant_general');

        // Le signataire des documents officiels (certificat de scolarité).
        Setting::set($school->id, 'chef_etablissement', $principal->name);

        $annee = AnneeScolaire::updateOrCreate(
            ['school_id' => $school->id, 'libelle' => '2026-2027'],
            ['date_debut' => '2026-09-01', 'date_fin' => '2027-06-30', 'is_active' => true]
        );

        $numSequences = (int) Setting::get($school->id, 'num_sequences', 2);

        foreach ([
            ['libelle' => 'Trimestre 1', 'ordre' => 1, 'date_debut' => '2026-09-01', 'date_fin' => '2026-12-19', 'is_active' => true],
            ['libelle' => 'Trimestre 2', 'ordre' => 2, 'date_debut' => '2027-01-05', 'date_fin' => '2027-03-27'],
            ['libelle' => 'Trimestre 3', 'ordre' => 3, 'date_debut' => '2027-04-06', 'date_fin' => '2027-06-30'],
        ] as $trimestreData) {
            $trimestre = Trimestre::updateOrCreate(
                ['annee_scolaire_id' => $annee->id, 'ordre' => $trimestreData['ordre']],
                $trimestreData
            );

            for ($i = 1; $i <= $numSequences; $i++) {
                Sequence::updateOrCreate(
                    ['trimestre_id' => $trimestre->id, 'ordre' => $i],
                    ['libelle' => "Séquence {$i}"]
                );
            }
        }

        $departements = collect(['Sciences', 'Lettres', 'Administration'])
            ->mapWithKeys(fn ($nom) => [$nom => Departement::updateOrCreate(
                ['school_id' => $school->id, 'nom' => $nom],
                ['school_id' => $school->id, 'nom' => $nom]
            )]);

        $enseignants = collect(self::ENSEIGNANTS)->map(fn ($data, $i) => Personnel::updateOrCreate(
            ['school_id' => $school->id, 'matricule' => sprintf('TID-%03d', $i + 1)],
            [
                'nom_complet' => $data['prenom'].' '.$data['nom'],
                'fonction_id' => $this->fonctionId($school, 'Enseignant', 'Teacher'),
                'departement_id' => $departements[$data['departement']]->id,
                'statut' => 'actif',
            ]
        ));

        // Encadrement : chaque responsable a sa fiche personnel et son accès.
        // Le surveillant général portait jusqu'ici le rôle `censeur_sg`, alors
        // que sa fonction disait l'inverse ; les deux concordent désormais.
        foreach ([
            ['TID-100', 'AKONO EVANG', 'Nathan', 'Surveillant Général', $surveillant],
            ['TID-101', 'NGUEMA', 'Alice', 'Censeur', $censeur],
            ['TID-102', 'USENI', 'Venyteh', 'Principal', $principal],
        ] as [$matricule, $nom, $prenom, $fonction, $compte]) {
            Personnel::updateOrCreate(
                ['school_id' => $school->id, 'matricule' => $matricule],
                [
                    'nom_complet' => $prenom.' '.$nom, 'fonction_id' => $this->fonctionId($school, $fonction),
                    'departement_id' => $departements['Administration']->id, 'statut' => 'actif',
                    'user_id' => $compte->id, 'email' => $compte->email,
                ]
            );
        }

        // Un accès par enseignant : au secondaire chacun ne saisit que les
        // matières qui lui sont affectées, règle qu'un compte d'encadrement
        // ne met pas à l'épreuve.
        $enseignants->each(function (Personnel $enseignant, int $i) use ($school, $niveauCollege) {
            $compte = $this->compte(
                $school,
                $niveauCollege->id,
                sprintf('enseignant%d@elites-school.test', $i + 1),
                $enseignant->nom_complet,
                'enseignant',
            );

            $enseignant->update(['user_id' => $compte->id, 'email' => $compte->email]);
        });

        // Chef de département (cf. _smapp departements.dept_head, ici une vraie FK).
        // Repere par matricule : `nom` n'existe plus sur Personnel, et les
        // matricules TID-001/TID-002 sont assignes juste au-dessus dans l'ordre
        // de self::ENSEIGNANTS (MELINGUI, puis ABENA).
        $departements['Sciences']->update(['head_personnel_id' => $enseignants->firstWhere('matricule', 'TID-001')->id]);
        $departements['Lettres']->update(['head_personnel_id' => $enseignants->firstWhere('matricule', 'TID-002')->id]);

        $classesDefinition = ['6ème A', '5ème A', '4ème A', '3ème A'];
        $classes = collect($classesDefinition)->map(fn ($nom, $i) => Classe::updateOrCreate(
            ['school_id' => $school->id, 'annee_scolaire_id' => $annee->id, 'nom' => $nom],
            [
                'niveau_id' => $niveauCollege->id,
                'filiere' => 'Général',
                // La 3ème présente le BEPC : donne une classe d'examen au
                // module Photos DECC & OBC dès le jeu de démonstration.
                'code_examen' => $nom === '3ème A' ? 'BEPC' : null,
                'capacite' => 60,
                'professeur_principal_id' => $enseignants[$i % $enseignants->count()]->id,
            ]
        ));

        $matieres = collect(self::MATIERES)->map(fn ($data) => Matiere::updateOrCreate(
            ['school_id' => $school->id, 'nom' => $data['nom']],
            [
                'abbreviation' => $data['abbr'],
                'departement_id' => $data['departement'] ? $departements[$data['departement']]->id : null,
            ]
        ));

        foreach ($classes as $classe) {
            foreach (self::MATIERES as $i => $matiereData) {
                ClasseMatiere::updateOrCreate(
                    ['classe_id' => $classe->id, 'matiere_id' => $matieres[$i]->id],
                    [
                        'personnel_id' => $enseignants[$i % $enseignants->count()]->id,
                        'coefficient' => $matiereData['coef'],
                        'quota_horaire' => $matiereData['coef'] + 1,
                        'groupe' => $matiereData['departement'] === 'Sciences' ? 1 : ($matiereData['departement'] === 'Lettres' ? 2 : 3),
                    ]
                );
            }
        }

        foreach (self::ELEVES as $i => $data) {
            $classe = $classes[$i % $classes->count()];

            $eleve = Eleve::updateOrCreate(
                ['school_id' => $school->id, 'matricule' => sprintf('ELV-%04d', $i + 1)],
                [
                    'classe_id' => $classe->id,
                    'nom_complet' => $data['prenom'].' '.$data['nom'],
                    'sexe' => $data['sexe'],
                    'statut' => 'actif',
                ]
            );

            $tuteur = Tuteur::firstOrCreate(
                ['school_id' => $school->id, 'telephone' => '69'.str_pad((string) (1000000 + $i), 7, '0', STR_PAD_LEFT)],
                [
                    'nom_complet' => ($data['sexe'] === 'F' ? 'Père de ' : 'Mère de ').$data['prenom'].' '.$data['nom'],
                ]
            );

            $eleve->tuteurs()->syncWithoutDetaching([$tuteur->id => ['lien_parente' => 'parent', 'is_principal' => true]]);
        }

        $this->seedNotesTrimestreActif($classes, $annee);
    }

    /**
     * Notes de démonstration (déterministes, pas de vrai aléatoire) pour le
     * trimestre actif, afin que moyennes/classements/palmarès/bulletins
     * soient testables dès le seed sans saisie manuelle.
     */
    /**
     * Maternelle et primaire du complexe : créées avec leur niveau et un compte
     * de direction rattaché, pour que la bascule d'établissement du super admin
     * soit démontrable. Leur flux pédagogique propre reste à construire.
     */
    private function fonctionId(School $school, string $labelFr, ?string $labelEn = null): int
    {
        return FonctionReferentiel::firstOrCreate(
            ['school_id' => $school->id, 'label_fr' => $labelFr],
            ['label_en' => $labelEn],
        )->id;
    }

    /** Compte de connexion de démonstration, mot de passe `password`. */
    private function compte(School $school, ?int $niveauId, string $email, string $nom, string $role): User
    {
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $nom,
                'password' => 'password',
                'school_id' => $school->id,
                'niveau_id' => $niveauId,
                'is_active' => true,
            ]
        );
        $user->syncRoles([$role]);

        return $user;
    }

    private function seedEcolesDuComplexe(Complexe $complexe): void
    {
        foreach ([
            ['code' => 'ELITES-MAT', 'type' => 'maternelle',
                'name' => 'Elites Bilingual Nursery School', 'email' => 'maternelle@elites-school.test'],
            ['code' => 'ELITES-PRI', 'type' => 'primaire',
                'name' => 'Elites Bilingual Primary School', 'email' => 'primaire@elites-school.test'],
        ] as $data) {
            $ecole = School::updateOrCreate(
                ['code' => $data['code']],
                [
                    'complexe_id' => $complexe->id,
                    'type' => $data['type'],
                    'name' => $data['name'],
                    'address' => 'Bertoua-Monou2, Cameroun',
                    'is_active' => true,
                ]
            );

            AnneeScolaire::updateOrCreate(
                ['school_id' => $ecole->id, 'libelle' => '2026-2027'],
                ['date_debut' => '2026-09-01', 'date_fin' => '2027-06-30', 'is_active' => true]
            );

            User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => 'Direction '.ucfirst($data['type']),
                    'password' => 'password',
                    'school_id' => $ecole->id,
                    'niveau_id' => $niveau?->id,
                    'is_active' => true,
                ]
            )->syncRoles(['admin_etablissement']);
        }
    }

    private function seedNotesTrimestreActif(Collection $classes, AnneeScolaire $annee): void
    {
        $trimestreActif = Trimestre::where('annee_scolaire_id', $annee->id)->where('is_active', true)->first();

        if (! $trimestreActif) {
            return;
        }

        $sequences = $trimestreActif->sequences;

        foreach ($classes as $classe) {
            $affectations = $classe->classeMatieres;
            $eleves = $classe->eleves;

            foreach ($affectations as $classeMatiere) {
                foreach ($eleves as $eleve) {
                    foreach ($sequences as $sequence) {
                        $graine = ($eleve->id * 31 + $classeMatiere->id * 17 + $sequence->ordre * 7) % 130;
                        $valeur = round(6 + $graine / 10, 2); // étale les notes entre 6.0 et ~19.0

                        Note::updateOrCreate(
                            ['eleve_id' => $eleve->id, 'classe_matiere_id' => $classeMatiere->id, 'sequence_id' => $sequence->id],
                            ['valeur' => $valeur]
                        );
                    }
                }
            }
        }
    }
}
