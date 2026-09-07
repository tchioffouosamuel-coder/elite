<?php

namespace App\Imports;

use App\Services\PreinscriptionService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use RuntimeException;

/**
 * Import massif d'une campagne de réinscription, à partir du même « fichier
 * de situation » que l'import élèves (cf. `EleveImport::COLONNES`) — même
 * en-têtes, pour ne pas demander à l'établissement un second format de
 * fichier. `prenom_eleve` est délibérément ignoré : `nom_eleves` porte déjà
 * le nom complet dans ce fichier.
 *
 * Ancien élève ou nouveau : jamais une colonne dédiée, toujours une
 * comparaison — matricule d'abord (s'il correspond réellement à un élève de
 * l'école), puis nom complet + date de naissance. Sans correspondance,
 * c'est un nouvel élève. Cf. `PreinscriptionService::importerLigne()` pour
 * la comparaison elle-même.
 */
class PreinscriptionImport implements SkipsEmptyRows, ToCollection, WithHeadingRow
{
    private const COLONNES = [
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

        'nom_classe' => 'classe',
        'classe' => 'classe',
        'niveau_classe' => 'niveau_classe',

        'adresse_parent' => 'adresse',
        'adresse' => 'adresse',
        'nom_parents' => 'pere_nom',
        'nom_pere' => 'pere_nom',
        'tel_pere' => 'pere_telephone',
        'fonction_pere' => 'pere_profession',
        'nom_mere' => 'mere_nom',
        'tel_mere' => 'mere_telephone',
        'fonction_mere' => 'mere_profession',
        'tel_autre' => 'tuteur_telephone',
        'tuteur_telephone' => 'tuteur_telephone',

        /*
         * Situation financière de l'année couverte par le fichier — même
         * lecture que EleveImport::COLONNES : « MONTANT_SCOLARITE » porte en
         * réalité ce qui a déjà été réglé, pas ce qui est dû.
         */
        'frais_scolarite' => 'scolarite_due',
        'montant_scolarite' => 'scolarite_payee',
        'remise_scol' => 'scolarite_remise',
        'annee_scol' => 'annee_source',

        /*
         * Le fichier porte aussi sa propre colonne « DEBTS » — en principe
         * égale à frais_scolarite - MONTANT_SCOLARITE - remise_scol (cf. le
         * commentaire ci-dessus), mais pas toujours : certaines lignes n'ont
         * pas les trois colonnes de calcul renseignées alors que DEBTS l'est.
         * Elle prime donc sur le calcul quand elle est présente — cf.
         * `PreinscriptionService::enregistrerDetteImport()`.
         */
        'debts' => 'dette_declaree',
        'dette' => 'dette_declaree',
    ];

    /** @return list<string> */
    public static function enTetes(): array
    {
        return [
            'IDEleves', 'nom_eleves', 'sexe_eleves', 'ddn_eleves', 'Nom_classe', 'niveau_classe',
            'nationalité', 'lieu_naiss', 'numero_acte_naissance', 'redoublant', 'refugies', 'deplace_interne',
            'adresse_parent', 'nom_parents', 'tel_pere', 'fonction_pere', 'nom_mere', 'tel_mere', 'fonction_mere',
            'tel_autre', 'frais_scolarite', 'MONTANT_SCOLARITE', 'remise_scol', 'annee_scol', 'DEBTS',
        ];
    }

    public int $importees = 0;

    /** @var list<array{ligne: int, message: string}> */
    public array $erreurs = [];

    public function __construct(
        private readonly int $schoolId,
        private readonly PreinscriptionService $service,
        private readonly int $adminUserId,
    ) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $numeroLigne = $index + 2; // +1 pour l'en-tête, +1 pour repasser en base 1.
            $ligne = $this->normaliser($row instanceof Collection ? $row->all() : $row);

            try {
                $this->service->importerLigne($this->schoolId, $ligne, $this->adminUserId);
                $this->importees++;
            } catch (RuntimeException $e) {
                $this->erreurs[] = ['ligne' => $numeroLigne, 'message' => $e->getMessage()];
            }
        }
    }

    /** @param array<string, mixed> $donnees */
    private function normaliser(array $donnees): array
    {
        $ligne = [];

        foreach ($donnees as $entete => $valeur) {
            $cle = self::COLONNES[$entete] ?? null;
            $valeur = self::nettoyer($valeur);

            if ($cle !== null && $valeur !== null && ! isset($ligne[$cle])) {
                $ligne[$cle] = $valeur;
            }
        }

        $contacts = [
            ['lien' => 'Père', 'nom' => isset($ligne['pere_nom']) ? self::texte($ligne['pere_nom']) : null, 'telephone' => self::telephone($ligne['pere_telephone'] ?? null), 'profession' => isset($ligne['pere_profession']) ? self::texte($ligne['pere_profession']) : null],
            ['lien' => 'Mère', 'nom' => isset($ligne['mere_nom']) ? self::texte($ligne['mere_nom']) : null, 'telephone' => self::telephone($ligne['mere_telephone'] ?? null), 'profession' => isset($ligne['mere_profession']) ? self::texte($ligne['mere_profession']) : null],
            ['lien' => 'Autre contact', 'nom' => null, 'telephone' => self::telephone($ligne['tuteur_telephone'] ?? null), 'profession' => null],
        ];

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
            'classe' => isset($ligne['classe']) ? self::texte($ligne['classe']) : null,
            'niveau_classe' => isset($ligne['niveau_classe']) ? self::texte($ligne['niveau_classe']) : null,
            'tuteurs' => array_filter($contacts, fn ($c) => $c['nom'] !== null || $c['telephone'] !== null),
            'scolarite_due' => self::montant($ligne['scolarite_due'] ?? null),
            'scolarite_payee' => self::montant($ligne['scolarite_payee'] ?? null),
            'scolarite_remise' => self::montant($ligne['scolarite_remise'] ?? null),
            'dette_declaree' => self::montant($ligne['dette_declaree'] ?? null),
            'annee_source' => isset($ligne['annee_source']) ? self::texte($ligne['annee_source']) : null,
        ];
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

    /** Le fichier de situation stocke les naissances en AAAAMMJJ ; les modèles saisis à la main utilisent une date Excel ou un format courant. */
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
