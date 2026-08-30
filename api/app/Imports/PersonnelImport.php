<?php

namespace App\Imports;

use App\Models\Classe;
use App\Models\Departement;
use App\Models\FonctionReferentiel;
use App\Models\Personnel;
use App\Services\CompteAgentService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithColumnLimit;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Import du tableau de mise en place du personnel (« GLOBAL STAFF STATUS »).
 *
 * Ce classeur est d'abord un outil de paie : ses en-têtes sont en troisième
 * ligne, la quatrième porte les taux de cotisation et non un agent, et les
 * colonnes d'identité se répètent à droite en tête de chaque bloc de calcul.
 * On n'en retient que le dossier administratif ; salaires, primes, impôts et
 * CNPS relèvent d'un module de paie qui n'existe pas encore.
 *
 * L'ancien modèle de fichier (`nom_complet`, `fonction`, `telephone`…) reste
 * accepté : les en-têtes sont ramenés à une clé normalisée avant d'être
 * traduits, de sorte que les deux formats — et leurs variantes de casse,
 * d'accents ou de ponctuation — tombent sur les mêmes champs.
 */
class PersonnelImport implements SkipsEmptyRows, SkipsOnFailure, ToCollection, WithColumnLimit, WithHeadingRow, WithValidation
{
    use SkipsFailures;

    /**
     * En-tête source (normalisé par self::cle) => clé canonique.
     *
     * Volontairement absents, faute d'équivalent en base : le numéro d'ordre,
     * l'ancienne affectation (« Duty poste 2021 ») et tout le bloc de paie.
     */
    private const COLONNES = [
        'teachersnamenomsdesenseignants' => 'nom_complet',
        'nomcomplet' => 'nom_complet',
        'noms' => 'nom_complet',
        'civilite' => 'civilite',
        'matricules' => 'matricule',
        'matricule' => 'matricule',
        'numerounique' => 'numero_cni',
        'cni' => 'numero_cni',
        'ncnps' => 'numero_cnps',
        'numerocnps' => 'numero_cnps',
        'births' => 'date_naissance',
        'datenaissance' => 'date_naissance',
        'datestart' => 'date_embauche',
        'dateembauche' => 'date_embauche',
        'dateend' => 'date_fin',
        'datefin' => 'date_fin',
        'dateretraite' => 'date_retraite',
        'divisionoforigine' => 'departement_origine',
        'departementorigine' => 'departement_origine',
        'residence' => 'residence',
        'orange' => 'telephone',
        'telephone' => 'telephone',
        'mtn' => 'telephone_2',
        'telephone2' => 'telephone_2',
        'married' => 'situation_matrimoniale',
        'situationmatrimoniale' => 'situation_matrimoniale',
        'nbchildren21yrs' => 'nombre_enfants',
        'nombreenfants' => 'nombre_enfants',
        'diplomeprof' => 'diplome_professionnel',
        'diplomeprofessionnel' => 'diplome_professionnel',
        'diplomeacademic' => 'diplome_academique',
        'diplomeacademique' => 'diplome_academique',
        'affectationsdutypost' => 'affectation',
        'affectation' => 'affectation',
        'fonction' => 'fonction',
        'email' => 'email',
        'departement' => 'departement',
        'npermis' => 'numero_permis',
        'numeropermis' => 'numero_permis',
        'typecontrat' => 'type_contrat',
        'statutcontrat' => 'statut_contrat',
        'categorieechelon' => 'categorie_echelon',
        'categorie' => 'categorie_echelon',
        'echelon' => 'categorie_echelon',
        'grademinedub' => 'grade_minedub',
        'grade' => 'grade_minedub',
        'absentdepuis' => 'absent_depuis',
        'motifabsence' => 'motif_absence',
        'dossierdisciplinaire' => 'dossier_disciplinaire',
        'datedeces' => 'date_deces',
        'banque' => 'banque',
        'ncompte' => 'numero_compte',
        'numerocompte' => 'numero_compte',
        'nomdupere' => 'pere_nom_complet',
        'perenomcomplet' => 'pere_nom_complet',
        'statutpere' => 'pere_statut',
        'telephonepere' => 'pere_telephone',
        'nomdelamere' => 'mere_nom_complet',
        'merenomcomplet' => 'mere_nom_complet',
        'statutmere' => 'mere_statut',
        'telephonemere' => 'mere_telephone',
    ];

    public int $importedCount = 0;

    public int $updatedCount = 0;

    /** Accès de connexion ouverts par cet import. */
    public int $comptesOuverts = 0;

    /**
     * Affectations conservées en texte faute de classe correspondante — postes
     * qui n'en sont pas une (« Bus driver »), classes d'une autre école du
     * complexe, ou libellés qui ne collent pas au référentiel de classes.
     *
     * @var array<string, int> libellé => nombre d'agents
     */
    public array $affectationsNonRattachees = [];

    /** @var array<string, int>|null clé normalisée => id de classe */
    private ?array $classes = null;

    public function __construct(private readonly int $schoolId) {}

    /**
     * Les en-têtes utiles sont en ligne 3 ; les deux premières portent le titre
     * du document et les totaux de la paie.
     */
    public function headingRow(): int
    {
        return 3;
    }

    /**
     * Le dossier administratif tient dans les colonnes A à T ; au-delà commence
     * la paie, découpée en quatre blocs qui répètent chacun les colonnes
     * d'identité — mais sous forme de formules (`=C5`).
     *
     * Ces répétitions ne sont pas anodines : les en-têtes étant identiques,
     * maatwebsite ne garde que la dernière occurrence, si bien que sans cette
     * borne chaque agent s'importait avec « =C5 » pour nom.
     */
    public function endColumn(): string
    {
        return 'T';
    }

    /**
     * La ligne qui suit les en-têtes contient les taux de cotisation, pas un
     * agent — comme toute ligne de sous-total en fin de tableau. Un agent se
     * reconnaît à son nom : sans nom, la ligne n'est pas un agent.
     *
     * @param  array<string, mixed>  $row
     */
    public function isEmptyWhen(array $row): bool
    {
        foreach ($row as $entete => $valeur) {
            if ((self::COLONNES[self::cle($entete)] ?? null) === 'nom_complet' && trim((string) $valeur) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepareForValidation(array $data, int $index): array
    {
        $ligne = [];

        foreach ($data as $entete => $valeur) {
            $cle = self::COLONNES[self::cle($entete)] ?? null;
            $valeur = self::nettoyer($valeur);

            if ($cle !== null && $valeur !== null && ! isset($ligne[$cle])) {
                $ligne[$cle] = $valeur;
            }
        }

        $civilite = isset($ligne['civilite']) ? self::texte($ligne['civilite']) : null;

        return [
            'nom_complet' => isset($ligne['nom_complet']) ? self::texte($ligne['nom_complet']) : null,
            'civilite' => $civilite,
            'sexe' => self::sexe($civilite),
            'matricule' => isset($ligne['matricule']) ? self::texte($ligne['matricule']) : null,
            'numero_cni' => self::identifiant($ligne['numero_cni'] ?? null),
            'numero_cnps' => self::identifiant($ligne['numero_cnps'] ?? null),
            'date_naissance' => self::date($ligne['date_naissance'] ?? null),
            'date_embauche' => self::date($ligne['date_embauche'] ?? null),
            'date_fin' => self::date($ligne['date_fin'] ?? null),
            'date_retraite' => self::date($ligne['date_retraite'] ?? null),
            'departement_origine' => isset($ligne['departement_origine']) ? self::texte($ligne['departement_origine']) : null,
            'residence' => isset($ligne['residence']) ? self::texte($ligne['residence']) : null,
            'telephone' => self::telephone($ligne['telephone'] ?? null),
            'telephone_2' => self::telephone($ligne['telephone_2'] ?? null),
            'situation_matrimoniale' => self::situationMatrimoniale($ligne['situation_matrimoniale'] ?? null),
            'nombre_enfants' => isset($ligne['nombre_enfants']) && is_numeric($ligne['nombre_enfants'])
                ? min(255, max(0, (int) $ligne['nombre_enfants']))
                : null,
            'diplome_professionnel' => isset($ligne['diplome_professionnel']) ? self::texte($ligne['diplome_professionnel']) : null,
            'diplome_academique' => isset($ligne['diplome_academique']) ? self::texte($ligne['diplome_academique']) : null,
            'email' => isset($ligne['email']) ? self::texte($ligne['email']) : null,
            'fonction' => isset($ligne['fonction']) ? self::texte($ligne['fonction']) : null,
            'affectation' => isset($ligne['affectation']) ? self::texte($ligne['affectation']) : null,
            'departement' => isset($ligne['departement']) ? self::texte($ligne['departement']) : null,
            'numero_permis' => isset($ligne['numero_permis']) ? self::texte($ligne['numero_permis']) : null,
            'type_contrat' => self::typeContrat($ligne['type_contrat'] ?? null),
            'statut_contrat' => self::statutContrat($ligne['statut_contrat'] ?? null),
            'categorie_echelon' => isset($ligne['categorie_echelon']) ? self::texte($ligne['categorie_echelon']) : null,
            'grade_minedub' => isset($ligne['grade_minedub']) ? self::texte($ligne['grade_minedub']) : null,
            'absent_depuis' => self::date($ligne['absent_depuis'] ?? null),
            'motif_absence' => isset($ligne['motif_absence']) ? self::texte($ligne['motif_absence']) : null,
            'dossier_disciplinaire' => self::booleen($ligne['dossier_disciplinaire'] ?? null),
            'date_deces' => self::date($ligne['date_deces'] ?? null),
            'banque' => isset($ligne['banque']) ? self::texte($ligne['banque']) : null,
            'numero_compte' => isset($ligne['numero_compte']) ? self::texte($ligne['numero_compte']) : null,
            'pere_nom_complet' => isset($ligne['pere_nom_complet']) ? self::texte($ligne['pere_nom_complet']) : null,
            'pere_statut' => self::statutParent($ligne['pere_statut'] ?? null),
            'pere_telephone' => self::telephone($ligne['pere_telephone'] ?? null),
            'mere_nom_complet' => isset($ligne['mere_nom_complet']) ? self::texte($ligne['mere_nom_complet']) : null,
            'mere_statut' => self::statutParent($ligne['mere_statut'] ?? null),
            'mere_telephone' => self::telephone($ligne['mere_telephone'] ?? null),
        ];
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $ligne = $row instanceof Collection ? $row->all() : $row;

            $personnel = $this->enregistrer($ligne);
            $this->rattacherClasse($personnel, $ligne['affectation']);

            // Chaque agent repris reçoit son accès. `wasRecentlyCreated`
            // distingue l'ouverture réelle du compte déjà en place : au
            // réimport, `assurer()` rend l'existant et rien n'a été ouvert.
            $compte = app(CompteAgentService::class)->assurer($personnel);

            if ($compte?->wasRecentlyCreated) {
                $this->comptesOuverts++;
            }
        }
    }

    public function rules(): array
    {
        return [
            'nom_complet' => ['required', 'string'],
            'email' => ['nullable', 'email'],
            'date_naissance' => ['nullable', 'date'],
            'date_embauche' => ['nullable', 'date'],
        ];
    }

    /**
     * Rapprochement sur le matricule quand il existe, sinon sur le nom : trois
     * agents du tableau n'en ont pas encore, et les créer en double à chaque
     * réimport reviendrait à recréer le désordre que le fichier corrige.
     *
     * @param  array<string, mixed>  $ligne
     */
    private function enregistrer(array $ligne): Personnel
    {
        $attributs = array_filter([
            'civilite' => $ligne['civilite'],
            'sexe' => $ligne['sexe'],
            'date_naissance' => $ligne['date_naissance'],
            'numero_cni' => $ligne['numero_cni'],
            'numero_cnps' => $ligne['numero_cnps'],
            'departement_origine' => $ligne['departement_origine'],
            'residence' => $ligne['residence'],
            'telephone' => $ligne['telephone'],
            'telephone_2' => $ligne['telephone_2'],
            'situation_matrimoniale' => $ligne['situation_matrimoniale'],
            'nombre_enfants' => $ligne['nombre_enfants'],
            'diplome_professionnel' => $ligne['diplome_professionnel'],
            'diplome_academique' => $ligne['diplome_academique'],
            'email' => $ligne['email'],
            'date_embauche' => $ligne['date_embauche'],
            'date_fin' => $ligne['date_fin'],
            'date_retraite' => $ligne['date_retraite'],
            'fonction_id' => $this->fonctionId($ligne),
            'departement_id' => $this->departementId($ligne),
            'affectation' => $ligne['affectation'],
            'numero_permis' => $ligne['numero_permis'],
            'type_contrat' => $ligne['type_contrat'],
            'statut_contrat' => $ligne['statut_contrat'],
            'categorie_echelon' => $ligne['categorie_echelon'],
            'grade_minedub' => $ligne['grade_minedub'],
            'absent_depuis' => $ligne['absent_depuis'],
            'motif_absence' => $ligne['motif_absence'],
            'dossier_disciplinaire' => $ligne['dossier_disciplinaire'],
            'date_deces' => $ligne['date_deces'],
            'banque' => $ligne['banque'],
            'numero_compte' => $ligne['numero_compte'],
            'pere_nom_complet' => $ligne['pere_nom_complet'],
            'pere_statut' => $ligne['pere_statut'],
            'pere_telephone' => $ligne['pere_telephone'],
            'mere_nom_complet' => $ligne['mere_nom_complet'],
            'mere_statut' => $ligne['mere_statut'],
            'mere_telephone' => $ligne['mere_telephone'],
        ], fn ($valeur) => $valeur !== null);

        // Un agent dont le contrat a une date de fin n'est plus en poste.
        $attributs['statut'] = $ligne['date_fin'] !== null ? 'ex_employe' : 'actif';

        $identite = $ligne['matricule'] !== null
            ? ['school_id' => $this->schoolId, 'matricule' => $ligne['matricule']]
            : ['school_id' => $this->schoolId, 'nom_complet' => $ligne['nom_complet']];

        $personnel = Personnel::updateOrCreate($identite, [...$attributs, 'nom_complet' => $ligne['nom_complet']]);

        $personnel->wasRecentlyCreated ? $this->importedCount++ : $this->updatedCount++;

        return $personnel;
    }

    /**
     * La fonction vient de la colonne dédiée si le fichier en a une ; à défaut,
     * le tableau de mise en place ne dit que l'affectation (« HM »,
     * « Nursery 1-A »). On ne devine pas une fonction à partir d'une classe :
     * mieux vaut laisser vide et la saisir, la fonction porte les privilèges.
     *
     * @param  array<string, mixed>  $ligne
     */
    private function fonctionId(array $ligne): ?int
    {
        $libelle = $ligne['fonction'];

        if ($libelle === null) {
            return null;
        }

        $fonction = FonctionReferentiel::forSchool($this->schoolId)
            ->whereRaw('LOWER(label_fr) = ?', [Str::lower($libelle)])
            ->first();

        // Une fonction inconnue est ajoutée au référentiel plutôt que de faire
        // échouer la ligne : l'import sert à reprendre un existant, pas à le
        // juger. Elle arrive sans privilège, à doter depuis l'écran dédié.
        return ($fonction ?: FonctionReferentiel::create([
            'school_id' => $this->schoolId,
            'label_fr' => $libelle,
        ]))->id;
    }

    /**
     * Même logique que {@see fonctionId()} : un département inconnu est créé
     * plutôt que de faire échouer la ligne ou de laisser l'agent orphelin.
     *
     * @param  array<string, mixed>  $ligne
     */
    private function departementId(array $ligne): ?int
    {
        $libelle = $ligne['departement'];

        if ($libelle === null) {
            return null;
        }

        $departement = Departement::forSchool($this->schoolId)
            ->whereRaw('LOWER(nom) = ?', [Str::lower($libelle)])
            ->first();

        return ($departement ?: Departement::create([
            'school_id' => $this->schoolId,
            'nom' => $libelle,
        ]))->id;
    }

    /**
     * Quand « Affectations / Duty post » désigne une classe de l'établissement,
     * l'agent en devient le titulaire. Sinon le libellé reste sur la fiche
     * (colonne `affectation`, renseignée à l'enregistrement) et il est remonté
     * à l'utilisateur : il vaut souvent « Bus driver » ou « Assistante GS »,
     * qui ne sont pas des classes, ou vise une classe d'une autre école du
     * complexe — le fichier couvre les trois.
     */
    private function rattacherClasse(Personnel $personnel, ?string $libelle): void
    {
        if ($libelle === null) {
            return;
        }

        $classeId = $this->classes()[self::cle($libelle)] ?? null;

        if ($classeId === null) {
            $this->affectationsNonRattachees[$libelle] = ($this->affectationsNonRattachees[$libelle] ?? 0) + 1;

            return;
        }

        Classe::whereKey($classeId)->update(['titulaire_id' => $personnel->id]);
    }

    /** @return array<string, int> */
    private function classes(): array
    {
        if ($this->classes !== null) {
            return $this->classes;
        }

        $this->classes = [];

        foreach (Classe::where('school_id', $this->schoolId)->get(['id', 'nom', 'sigle']) as $classe) {
            foreach ([$classe->nom, $classe->sigle] as $libelle) {
                $cle = self::cle($libelle);

                if ($cle !== '' && ! isset($this->classes[$cle])) {
                    $this->classes[$cle] = $classe->id;
                }
            }
        }

        return $this->classes;
    }

    /**
     * Clé insensible à la casse, aux accents, aux espaces et à la ponctuation.
     * Sert aussi bien à reconnaître un en-tête qu'à rapprocher une classe : les
     * deux côtés de la comparaison passent toujours par ici.
     */
    private static function cle(?string $valeur): string
    {
        return preg_replace('/[^a-z0-9]+/', '', mb_strtolower(Str::ascii((string) $valeur))) ?? '';
    }

    private static function nettoyer(mixed $valeur): mixed
    {
        if (is_string($valeur)) {
            $valeur = trim($valeur);
        }

        return ($valeur === '' || $valeur === null) ? null : $valeur;
    }

    private static function texte(mixed $valeur): ?string
    {
        return self::nettoyer(preg_replace('/\s+/u', ' ', (string) $valeur));
    }

    /**
     * CNI et numéro CNPS : le tableau porte des cellules réduites à une
     * ponctuation (« , ») là où l'information manque encore.
     */
    private static function identifiant(mixed $valeur): ?string
    {
        $texte = self::texte($valeur);

        return ($texte === null || preg_match('/[A-Z0-9]/i', $texte) !== 1) ? null : $texte;
    }

    /** Mrs, Mme, Mlle, Miss → F ; Mr, M. → M. */
    private static function sexe(?string $civilite): ?string
    {
        $cle = self::cle($civilite);

        return match (true) {
            $cle === '' => null,
            in_array($cle, ['mrs', 'mme', 'mlle', 'miss', 'madame', 'mademoiselle'], true) => 'F',
            in_array($cle, ['mr', 'm', 'monsieur'], true) => 'M',
            default => null,
        };
    }

    /** Le tableau mélange français et anglais, avec et sans accent. */
    private static function situationMatrimoniale(mixed $valeur): ?string
    {
        $cle = self::cle(self::texte($valeur));

        // « Célibataire », « Celibataire », « Mariée », « Maried », « Married » :
        // le tableau mélange les deux langues, avec et sans accent, et les
        // fautes de frappe sont trop répandues pour une correspondance exacte.
        return match (true) {
            $cle === '' => null,
            str_starts_with($cle, 'celibataire'), str_starts_with($cle, 'single') => 'celibataire',
            str_starts_with($cle, 'mari'), str_starts_with($cle, 'marr') => 'marie',
            str_starts_with($cle, 'divorc') => 'divorce',
            str_starts_with($cle, 'veu'), str_starts_with($cle, 'widow') => 'veuf',
            default => null,
        };
    }

    private static function typeContrat(mixed $valeur): ?string
    {
        $cle = self::cle(self::texte($valeur));

        return match (true) {
            $cle === '' => null,
            str_starts_with($cle, 'cdi') => 'CDI',
            str_starts_with($cle, 'cdd') => 'CDD',
            default => null,
        };
    }

    private static function statutContrat(mixed $valeur): ?string
    {
        $cle = self::cle(self::texte($valeur));

        return match (true) {
            $cle === '' => null,
            str_starts_with($cle, 'essai') => 'essai',
            str_starts_with($cle, 'perman') => 'permanent',
            str_starts_with($cle, 'vacat') => 'vacataire',
            default => null,
        };
    }

    /** Vivant/décédé, dans les deux langues : même tolérance que `situationMatrimoniale()`. */
    private static function statutParent(mixed $valeur): ?string
    {
        $cle = self::cle(self::texte($valeur));

        return match (true) {
            $cle === '' => null,
            str_starts_with($cle, 'vivant'), str_starts_with($cle, 'alive'), str_starts_with($cle, 'living') => 'vivant',
            str_starts_with($cle, 'dece'), str_starts_with($cle, 'dead') => 'decede',
            default => null,
        };
    }

    /** Oui/Non, Yes/No, 1/0 : les variantes usuelles d'une case à cocher retranscrite en Excel. */
    private static function booleen(mixed $valeur): ?bool
    {
        $cle = self::cle(self::texte($valeur));

        return match (true) {
            $cle === '' => null,
            in_array($cle, ['oui', 'yes', '1', 'vrai', 'true'], true) => true,
            in_array($cle, ['non', 'no', '0', 'faux', 'false'], true) => false,
            default => null,
        };
    }

    private static function telephone(mixed $valeur): ?string
    {
        $chiffres = preg_replace('/\D+/', '', (string) ($valeur ?? ''));

        return ($chiffres === '' || ltrim($chiffres, '0') === '') ? null : $chiffres;
    }

    private static function date(mixed $valeur): ?string
    {
        if ($valeur === null) {
            return null;
        }

        if ($valeur instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($valeur)->toDateString();
        }

        $texte = trim((string) $valeur);

        if (is_numeric($texte) && ! preg_match('/^\d{8}$/', $texte)) {
            try {
                return CarbonImmutable::instance(ExcelDate::excelToDateTimeObject((float) $texte))->toDateString();
            } catch (\Throwable) {
                return null;
            }
        }

        foreach (['!Ymd', '!Y-m-d', '!d/m/Y', '!d-m-Y', '!Y/m/d'] as $format) {
            try {
                return CarbonImmutable::createFromFormat($format, $texte)->toDateString();
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }
}
