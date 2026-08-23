<?php

namespace App\Imports;

use App\Models\Classe;
use App\Models\DetteAnterieure;
use App\Models\Eleve;
use App\Models\School;
use App\Models\Tuteur;
use App\Services\ScolariteService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Import du fichier de situation scolaire (un onglet, une ligne par élève).
 *
 * Les en-têtes sont normalisés par maatwebsite (slug minuscule) puis traduits
 * en clés canoniques via self::COLONNES : le fichier métier
 * (`IDEleves`, `nom_eleves`, `ddn_eleves`, `Nom_classe`…) et l'ancien modèle
 * (`matricule`, `nom_complet`, `classe`…) sont donc tous les deux acceptés.
 *
 * ToCollection plutôt que ToModel : chaque ligne doit résoudre une classe par
 * nom et créer jusqu'à trois tuteurs — une simple correspondance ligne→modèle
 * ne suffit pas.
 */
class EleveImport implements SkipsEmptyRows, SkipsOnFailure, ToCollection, WithHeadingRow, WithValidation
{
    use SkipsFailures;

    /**
     * En-tête source (slug) => clé canonique. Plusieurs en-têtes peuvent viser
     * la même clé ; la première colonne renseignée l'emporte.
     *
     * Sans équivalent en base, donc volontairement absents : `observation`,
     * `statut_inscription` (situation financière), `communaute_enfant` et
     * `sous_systeme` (porté par la classe, pas par l'élève).
     */
    private const COLONNES = [
        // Identité de l'élève
        'ideleves' => 'matricule',
        'id_eleves' => 'matricule',
        'matricule' => 'matricule',
        'nom_eleves' => 'nom_complet',
        'nom_eleve' => 'nom_complet',
        'nom_complet' => 'nom_complet',
        'sexe_eleves' => 'sexe',
        'sexe' => 'sexe',
        'ddn_eleves' => 'date_naissance',
        'ddn' => 'date_naissance',
        'date_naissance' => 'date_naissance',
        'lieu_naiss' => 'lieu_naissance',
        'lieu_naissance' => 'lieu_naissance',
        'nationalite' => 'nationalite',
        'numero_acte_naissance' => 'numero_acte_naissance',
        'redoublant' => 'redoublant',
        'refugies' => 'refugie',
        'refugie' => 'refugie',
        'deplace_interne' => 'deplace_interne',
        'etat_eleves' => 'statut',
        'statut' => 'statut',

        // Affectation
        'nom_classe' => 'classe',
        'classe' => 'classe',
        'niveau_classe' => 'niveau_classe',
        'categorie_ecole' => 'categorie_ecole',

        // Parents et contacts
        'adresse_parent' => 'adresse',
        'adresse' => 'adresse',
        'nom_parents' => 'pere_nom',
        'nom_pere' => 'pere_nom',
        'tel_pere' => 'pere_telephone',
        'fonction_pere' => 'pere_profession',
        'nom_mere' => 'mere_nom',
        'tel_mere' => 'mere_telephone',
        'fonction_mere' => 'mere_profession',
        'tuteur_nom_complet' => 'tuteur_nom',
        'tel_autre' => 'tuteur_telephone',
        'tuteur_telephone' => 'tuteur_telephone',

        /*
         * Situation financière de l'année couverte par le fichier. Le nom de
         * la colonne source (« MONTANT_SCOLARITE ») est trompeur : la formule
         * du fichier (frais_scolarite - MONTANT_SCOLARITE - remise_scol =
         * DEBTS) ne se vérifie que si elle porte le montant déjà réglé, pas le
         * montant dû — c'est ce que confirme un « Reglé » chaque fois que les
         * deux colonnes sont égales.
         */
        'frais_scolarite' => 'scolarite_due',
        'montant_scolarite' => 'scolarite_payee',
        'remise_scol' => 'scolarite_remise',
        'annee_scol' => 'annee_source',
    ];

    public int $importedCount = 0;

    public int $updatedCount = 0;

    /** Dette antérieure enregistrée (report_dette du prochain dossier ouvert). */
    public int $dettesCount = 0;

    public int $dettesMontantTotal = 0;

    /**
     * Lignes dont la dette calculée avait déjà été importée — le fichier de
     * situation est réexporté et réimporté au fil de l'année, sans qu'on
     * veuille dupliquer le report à chaque passage.
     */
    public int $dettesIgnoreesCount = 0;

    /** Lignes appartenant à une autre école du complexe (cf. `categorie_ecole`). */
    public int $ignoredCount = 0;

    /**
     * Total des lignes de données lues, toutes écoles confondues — sert de
     * référence à `EleveService::importPourToutesLesEcoles()` pour établir un
     * compte de lignes non attribuées qui ne double pas en sommant les
     * `ignoredCount` de plusieurs écoles (chacun compte les lignes des
     * *autres* écoles, qui se recoupent forcément entre deux imports).
     */
    public int $lignesCount = 0;

    /** @var array<string, int> libellé de classe non résolu => nombre de lignes */
    public array $classesIntrouvables = [];

    /** @var array<string, int>|null clé normalisée (nom ou sigle) => id de classe */
    private ?array $classes = null;

    private ?string $typeEcole = null;

    private bool $typeEcoleCharge = false;

    public function __construct(
        private readonly int $schoolId,
        private readonly ScolariteService $scolarite,
        private readonly ?int $importePar = null,
    ) {}

    /**
     * Appelé par maatwebsite avant validation, sur la ligne brute : c'est aussi
     * la ligne que reçoit collection(). On y fait toute la traduction
     * en-têtes → clés canoniques et le nettoyage des valeurs, pour que rules()
     * et collection() travaillent sur un format unique.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepareForValidation(array $data, int $index): array
    {
        $ligne = [];

        foreach ($data as $entete => $valeur) {
            $cle = self::COLONNES[$entete] ?? null;
            $valeur = self::nettoyer($valeur);

            if ($cle !== null && $valeur !== null && ! isset($ligne[$cle])) {
                $ligne[$cle] = $valeur;
            }
        }

        return [
            'matricule' => isset($ligne['matricule']) ? (string) $ligne['matricule'] : null,
            'nom_complet' => isset($ligne['nom_complet']) ? self::texte($ligne['nom_complet']) : null,
            'sexe' => isset($ligne['sexe']) ? mb_strtoupper(mb_substr((string) $ligne['sexe'], 0, 1)) : null,
            'date_naissance' => self::date($ligne['date_naissance'] ?? null),
            'lieu_naissance' => isset($ligne['lieu_naissance']) ? self::texte($ligne['lieu_naissance']) : null,
            'nationalite' => isset($ligne['nationalite']) ? self::texte($ligne['nationalite']) : null,
            'numero_acte_naissance' => isset($ligne['numero_acte_naissance']) ? (string) $ligne['numero_acte_naissance'] : null,
            'adresse' => isset($ligne['adresse']) ? self::texte($ligne['adresse']) : null,
            'redoublant' => self::booleen($ligne['redoublant'] ?? null),
            'refugie' => self::ouiNon($ligne['refugie'] ?? null),
            'deplace_interne' => self::ouiNon($ligne['deplace_interne'] ?? null),
            'statut' => self::statut($ligne['statut'] ?? null),
            'classe' => isset($ligne['classe']) ? self::texte($ligne['classe']) : null,
            'niveau_classe' => isset($ligne['niveau_classe']) ? self::texte($ligne['niveau_classe']) : null,
            'categorie_ecole' => isset($ligne['categorie_ecole']) ? self::texte($ligne['categorie_ecole']) : null,
            'pere_nom' => isset($ligne['pere_nom']) ? self::texte($ligne['pere_nom']) : null,
            'pere_telephone' => self::telephone($ligne['pere_telephone'] ?? null),
            'pere_profession' => isset($ligne['pere_profession']) ? self::texte($ligne['pere_profession']) : null,
            'mere_nom' => isset($ligne['mere_nom']) ? self::texte($ligne['mere_nom']) : null,
            'mere_telephone' => self::telephone($ligne['mere_telephone'] ?? null),
            'mere_profession' => isset($ligne['mere_profession']) ? self::texte($ligne['mere_profession']) : null,
            'tuteur_nom' => isset($ligne['tuteur_nom']) ? self::texte($ligne['tuteur_nom']) : null,
            'tuteur_telephone' => self::telephone($ligne['tuteur_telephone'] ?? null),
            'scolarite_due' => self::montant($ligne['scolarite_due'] ?? null),
            'scolarite_payee' => self::montant($ligne['scolarite_payee'] ?? null),
            'scolarite_remise' => self::montant($ligne['scolarite_remise'] ?? null),
            'annee_source' => isset($ligne['annee_source']) ? self::texte($ligne['annee_source']) : null,
        ];
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $ligne = $row instanceof Collection ? $row->all() : $row;
            $this->lignesCount++;

            if (! $this->concerneCetteEcole($ligne)) {
                $this->ignoredCount++;

                continue;
            }

            $eleve = $this->enregistrerEleve($ligne);
            $this->rattacherTuteurs($eleve, $ligne);
            $this->traiterDette($eleve, $ligne);
        }
    }

    public function rules(): array
    {
        return [
            'nom_complet' => ['required', 'string'],
            'sexe' => ['required', 'in:M,F'],
            'date_naissance' => ['nullable', 'date'],
        ];
    }

    /**
     * En-têtes du fichier, normalisés comme le fait maatwebsite (formateur
     * « slug » par défaut) : sert à sonder la présence de `categorie_ecole`
     * avant de lancer un import multi-écoles, sans dépendre de l'ordre dans
     * lequel Excel::import() lira effectivement le fichier.
     */
    public static function porteColonneCategorieEcole(\Illuminate\Http\UploadedFile $fichier): bool
    {
        $lecteur = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($fichier->getRealPath());
        $lecteur->setReadDataOnly(true);
        $feuille = $lecteur->load($fichier->getRealPath())->getSheet(0);

        foreach ($feuille->getRowIterator(1, 1) as $ligneEntete) {
            foreach ($ligneEntete->getCellIterator() as $cellule) {
                $slug = Str::slug((string) $cellule->getValue(), '_');

                if ((self::COLONNES[$slug] ?? null) === 'categorie_ecole') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Le fichier de situation couvre tout le complexe (maternelle, primaire,
     * secondaire) : on ne retient que les lignes de l'école courante. Sans
     * colonne `categorie_ecole`, on importe tout.
     *
     * @param  array<string, mixed>  $ligne
     */
    private function concerneCetteEcole(array $ligne): bool
    {
        $categorie = $ligne['categorie_ecole'] ?? null;
        $type = $this->typeEcole();

        if ($categorie === null || $type === null) {
            return true;
        }

        // « secondaire technique » relève d'une école de type « secondaire ».
        return str_starts_with(self::cle($categorie), self::cle($type));
    }

    /**
     * updateOrCreate sur (school_id, matricule) — la paire est unique en base :
     * réimporter une situation mise à jour rafraîchit les élèves existants au
     * lieu de planter sur un doublon. Les valeurs nulles sont écartées pour ne
     * pas écraser des données saisies à la main avec des colonnes vides.
     *
     * @param  array<string, mixed>  $ligne
     */
    private function enregistrerEleve(array $ligne): Eleve
    {
        $attributs = array_filter([
            'classe_id' => $this->classeId($ligne),
            'nom_complet' => $ligne['nom_complet'],
            'sexe' => $ligne['sexe'],
            'date_naissance' => $ligne['date_naissance'],
            'lieu_naissance' => $ligne['lieu_naissance'],
            'nationalite' => $ligne['nationalite'],
            'numero_acte_naissance' => $ligne['numero_acte_naissance'],
            'adresse' => $ligne['adresse'],
            'redoublant' => $ligne['redoublant'],
            'refugie' => $ligne['refugie'],
            'deplace_interne' => $ligne['deplace_interne'],
            'statut' => $ligne['statut'],
        ], fn ($valeur) => $valeur !== null);

        $eleve = Eleve::updateOrCreate([
            'school_id' => $this->schoolId,
            'matricule' => $ligne['matricule'] ?: Eleve::genererMatricule($this->schoolId),
        ], $attributs);

        $eleve->wasRecentlyCreated ? $this->importedCount++ : $this->updatedCount++;

        return $eleve;
    }

    /**
     * `Nom_classe` d'abord, `niveau_classe` en repli. Une classe absente de la
     * base n'invalide pas la ligne : l'élève est créé sans affectation et le
     * libellé est remonté à l'utilisateur, qui peut créer la classe puis
     * relancer l'import (idempotent) pour la rattacher.
     *
     * @param  array<string, mixed>  $ligne
     */
    private function classeId(array $ligne): ?int
    {
        $libelles = array_filter([$ligne['classe'], $ligne['niveau_classe']]);

        foreach ($libelles as $libelle) {
            $cle = self::cle($libelle);

            // Le fichier suffixe la section (« ACCOUNTING 1-A ») là où la base
            // ne nomme parfois que le niveau (« ACCOUNTING 1 ») : on retente
            // sans la lettre de section, mais seulement après l'avoir cherchée
            // telle quelle, pour ne jamais confondre deux sections existantes.
            foreach ([$cle, preg_replace('/(?<=\d)[A-Z]$/', '', $cle)] as $candidat) {
                if ($id = $this->classes()[$candidat] ?? null) {
                    return $id;
                }
            }
        }

        if ($libelles !== []) {
            $premier = reset($libelles);
            $this->classesIntrouvables[$premier] = ($this->classesIntrouvables[$premier] ?? 0) + 1;
        }

        return null;
    }

    /**
     * Père, mère et tuteur/contact supplémentaire. Un contact réduit à un
     * numéro déjà rattaché à l'élève est ignoré : dans les fichiers de
     * situation, `tel_autre` reprend le plus souvent `tel_pere` ou `tel_mere`.
     *
     * @param  array<string, mixed>  $ligne
     */
    private function rattacherTuteurs(Eleve $eleve, array $ligne): void
    {
        $contacts = [
            ['lien' => 'Père', 'nom' => $ligne['pere_nom'], 'telephone' => $ligne['pere_telephone'], 'profession' => $ligne['pere_profession']],
            ['lien' => 'Mère', 'nom' => $ligne['mere_nom'], 'telephone' => $ligne['mere_telephone'], 'profession' => $ligne['mere_profession']],
            ['lien' => 'Tuteur', 'nom' => $ligne['tuteur_nom'], 'telephone' => $ligne['tuteur_telephone'], 'profession' => null],
        ];

        $rattachements = [];
        $telephonesVus = [];

        foreach ($contacts as $contact) {
            if ($contact['nom'] === null && $contact['telephone'] === null) {
                continue;
            }

            if ($contact['nom'] === null && in_array($contact['telephone'], $telephonesVus, true)) {
                continue;
            }

            $tuteur = $this->tuteur($contact, $eleve, $ligne['adresse']);

            if ($contact['telephone'] !== null) {
                $telephonesVus[] = $contact['telephone'];
            }

            $rattachements[$tuteur->id] = [
                'lien_parente' => $contact['lien'],
                'is_principal' => $rattachements === [],
            ];
        }

        // sync() ferait autorité y compris pour une ligne muette : sur un
        // réimport, une ligne sans aucun contact détacherait les tuteurs saisis
        // à la main. Elle ne dit rien des tuteurs, on ne touche donc à rien.
        if ($rattachements !== []) {
            $eleve->tuteurs()->sync($rattachements);
        }
    }

    /**
     * Reprend en dette antérieure ce que le fichier de situation dit encore dû
     * pour son année (frais - montant réglé - remise). Une ligne soldée ou en
     * trop-perçu ne crée rien : `enregistrerDetteAnterieure` refuse un montant
     * nul ou négatif.
     *
     * Idempotent sur (élève, année source) via le motif : réimporter le même
     * fichier de situation, à jour ou non, ne double pas le report. Une
     * nouvelle année source (le prochain export de fin d'année) crée en
     * revanche sa propre dette, distincte de celle déjà reprise.
     *
     * @param  array<string, mixed>  $ligne
     */
    private function traiterDette(Eleve $eleve, array $ligne): void
    {
        $du = $ligne['scolarite_due'] ?? null;

        if ($du === null) {
            return;
        }

        $dette = $du - ($ligne['scolarite_payee'] ?? 0) - ($ligne['scolarite_remise'] ?? 0);

        if ($dette <= 0) {
            return;
        }

        $motif = 'Report scolarité '.($ligne['annee_source'] ?? 'année antérieure').' (import situation)';

        $dejaImportee = DetteAnterieure::where('eleve_id', $eleve->id)->where('motif', $motif)->exists();

        if ($dejaImportee) {
            $this->dettesIgnoreesCount++;

            return;
        }

        $this->scolarite->enregistrerDetteAnterieure($eleve, $dette, $motif, $this->importePar);
        $this->dettesCount++;
        $this->dettesMontantTotal += $dette;
    }

    /**
     * Recherche par téléphone si connu — les fratries partagent le même numéro
     * et doivent partager le même tuteur — sinon par nom. Le fichier ne nomme
     * les parents que sur une minorité de lignes : à défaut, on crée le contact
     * sous un libellé explicite plutôt que de perdre le numéro.
     *
     * @param  array{lien: string, nom: ?string, telephone: ?string, profession: ?string}  $contact
     */
    private function tuteur(array $contact, Eleve $eleve, ?string $adresse): Tuteur
    {
        $tuteur = Tuteur::firstOrNew($contact['telephone'] !== null
            ? ['school_id' => $this->schoolId, 'telephone' => $contact['telephone']]
            : ['school_id' => $this->schoolId, 'nom_complet' => $contact['nom']]);

        if ($contact['nom'] !== null) {
            $tuteur->nom_complet = $contact['nom'];
        } elseif (! $tuteur->exists) {
            $tuteur->nom_complet = $contact['lien'].' de '.$eleve->nom_complet;
        }

        $tuteur->profession = $contact['profession'] ?? $tuteur->profession;
        $tuteur->adresse = $adresse ?? $tuteur->adresse;
        $tuteur->save();

        return $tuteur;
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

    private function typeEcole(): ?string
    {
        if (! $this->typeEcoleCharge) {
            $this->typeEcole = School::find($this->schoolId)?->type;
            $this->typeEcoleCharge = true;
        }

        return $this->typeEcole;
    }

    /**
     * Clé de rapprochement insensible à la casse, aux accents, aux espaces et
     * aux tirets : « CLASS 4-A », « Class 4 A » et « class4a » se rejoignent.
     */
    private static function cle(?string $libelle): string
    {
        return preg_replace('/[^A-Z0-9]+/', '', mb_strtoupper(Str::ascii((string) $libelle))) ?? '';
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
     * Le fichier de situation stocke les naissances en AAAAMMJJ ; les modèles
     * saisis à la main utilisent plutôt une date Excel ou un format courant.
     */
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

    private static function booleen(mixed $valeur): ?bool
    {
        if ($valeur === null) {
            return null;
        }

        return in_array(mb_strtoupper(trim((string) $valeur)), ['OUI', 'O', 'YES', 'Y', '1', 'TRUE', 'VRAI'], true);
    }

    /** Colonne `enum('Oui','Non')` : la casse doit être respectée. */
    private static function ouiNon(mixed $valeur): ?string
    {
        return match (self::booleen($valeur)) {
            true => 'Oui',
            false => 'Non',
            null => null,
        };
    }

    /**
     * `etat_eleves` vaut Actif/Inactif ; l'énumération en base distingue
     * actif/parti/exclu. Un élève inactif est enregistré comme « parti », le
     * motif d'exclusion ne figurant pas dans le fichier.
     */
    private static function statut(mixed $valeur): ?string
    {
        $texte = mb_strtolower(trim((string) ($valeur ?? '')));

        return match (true) {
            $texte === '' => null,
            in_array($texte, ['actif', 'active'], true) => 'actif',
            in_array($texte, ['exclu', 'exclue', 'expelled'], true) => 'exclu',
            default => 'parti',
        };
    }

    private static function telephone(mixed $valeur): ?string
    {
        $chiffres = preg_replace('/\D+/', '', (string) ($valeur ?? ''));

        return ($chiffres === '' || ltrim($chiffres, '0') === '') ? null : $chiffres;
    }

    /** Le fichier écrit parfois ses montants en texte (« 90 000 », « 90000,00 »). */
    private static function montant(mixed $valeur): ?int
    {
        if ($valeur === null) {
            return null;
        }

        $nombre = preg_replace('/[^\d-]/', '', str_replace(',', '.', (string) $valeur));

        return $nombre === '' || $nombre === '-' ? null : (int) round((float) $nombre);
    }
}
