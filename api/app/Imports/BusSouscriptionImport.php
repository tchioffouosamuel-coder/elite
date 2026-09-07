<?php

namespace App\Imports;

use App\Models\AnneeScolaire;
use App\Models\BusAffectation;
use App\Models\BusArret;
use App\Models\BusTrajet;
use App\Models\Eleve;
use App\Services\BusPaiementService;
use App\Services\BusService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;

/**
 * Import massif d'une situation de transport scolaire : chaque ligne est un
 * mois payé par un élève pour un bus donné. Plusieurs lignes du même élève
 * (une par mois réglé) partagent la même souscription (`BusAffectation`),
 * retrouvée ou créée au fil du fichier, puis chacune devient un versement
 * distinct (`BusPaiementService::encaisser()`) — jamais fusionnées : deux
 * mois payés le même jour restent deux reçus.
 *
 * Les lignes sont groupées par élève avant traitement : la souscription doit
 * exister (et couvrir le plus ancien mois du groupe) avant que son premier
 * versement ne soit encaissé.
 */
class BusSouscriptionImport implements SkipsEmptyRows, ToCollection, WithHeadingRow
{
    private const COLONNES = [
        'matricule' => 'matricule',
        'ideleves' => 'matricule',
        'nom' => 'nom_complet',
        'nom_eleve' => 'nom_complet',
        'nom_complet' => 'nom_complet',
        'bus' => 'trajet',
        'trajet' => 'trajet',
        'date' => 'date_versement',
        'date_versement' => 'date_versement',
        'classe' => 'classe',
        'tarif' => 'montant',
        'montant' => 'montant',
        'saisi_par' => 'saisi_par',
        'arret' => 'arret',
        'lieu' => 'arret',
        'lieu_dit' => 'arret',
        'mois' => 'mois',
        'option' => 'option',
        'option_trajet' => 'option',
        'mode' => 'mode',
        'mode_paiement' => 'mode',
    ];

    /** Mois abrégés tels qu'utilisés dans le fichier (français, quelques variantes anglaises tolérées). */
    private const MOIS = [
        'jan' => 1, 'janv' => 1, 'janvier' => 1,
        'fev' => 2, 'fevr' => 2, 'fevrier' => 2, 'feb' => 2,
        'mar' => 3, 'mars' => 3,
        'avr' => 4, 'avril' => 4, 'apr' => 4,
        'mai' => 5, 'may' => 5,
        'jun' => 6, 'juin' => 6,
        'jul' => 7, 'juil' => 7, 'juillet' => 7,
        'aou' => 8, 'aout' => 8, 'aug' => 8,
        'sep' => 9, 'sept' => 9, 'septembre' => 9,
        'oct' => 10, 'octobre' => 10,
        'nov' => 11, 'novembre' => 11,
        'dec' => 12, 'decembre' => 12,
    ];

    private const MODES = [
        'cash' => 'especes',
        'especes' => 'especes',
        'espece' => 'especes',
        'om' => 'mobile_money',
        'orange_money' => 'mobile_money',
        'momo' => 'mobile_money',
        'mobile_money' => 'mobile_money',
        'virement' => 'virement',
        'cheque' => 'cheque',
        'depot' => 'depot_bancaire',
        'depot_bancaire' => 'depot_bancaire',
    ];

    /** @return list<string> */
    public static function enTetes(): array
    {
        return ['Matricule', 'Nom', 'Bus', 'Date', 'Classe', 'Tarif', 'Arrêt', 'Mois', 'Option', 'Mode'];
    }

    public int $versementsCrees = 0;

    public int $versementsIgnores = 0;

    /** @var list<array{ligne: int, message: string}> */
    public array $erreurs = [];

    /** @var array<string, BusTrajet>|null clé normalisée => trajet */
    private ?array $trajets = null;

    public function __construct(
        private readonly int $schoolId,
        private readonly BusService $busService,
        private readonly BusPaiementService $paiementService,
        private readonly int $encaissePar,
    ) {}

    public function collection(Collection $rows): void
    {
        $lignesNormalisees = $rows->map(fn ($row, $index) => [
            'numero' => $index + 2,
            'donnees' => $this->normaliser($row instanceof Collection ? $row->all() : $row),
        ]);

        // Groupées par élève (matricule, à défaut nom complet) : une seule
        // souscription à retrouver/créer par élève, avant que ses versements
        // ne puissent être encaissés un par un.
        $groupes = $lignesNormalisees->groupBy(fn (array $l) => $l['donnees']['matricule'] ?? $l['donnees']['nom_complet'] ?? '__vide__');

        foreach ($groupes as $cle => $lignes) {
            if ($cle === '__vide__') {
                foreach ($lignes as $ligne) {
                    $this->erreurs[] = ['ligne' => $ligne['numero'], 'message' => 'Ni matricule ni nom renseigné.'];
                }

                continue;
            }

            $this->traiterGroupe($lignes);
        }
    }

    /** @param Collection<int, array{numero: int, donnees: array}> $lignes */
    private function traiterGroupe(Collection $lignes): void
    {
        $premiere = $lignes->first()['donnees'];

        try {
            $eleve = $this->resoudreEleve($premiere);
            $trajet = $this->resoudreTrajet($premiere['trajet'] ?? null);
            $arret = $this->resoudreArret($trajet, $premiere['arret'] ?? null);
            $option = self::normaliserOption($premiere['option'] ?? null);

            $premierMois = $lignes
                ->map(fn (array $l) => $this->resoudreMois($eleve->school_id, $l['donnees']))
                ->filter()
                ->sort()
                ->first();

            $affectation = $this->busService->resoudreOuCreerAffectationPourImport(
                $eleve,
                $trajet,
                $arret,
                $option,
                $premierMois ?? Carbon::now()->startOfMonth(),
            );
        } catch (RuntimeException $e) {
            foreach ($lignes as $ligne) {
                $this->erreurs[] = ['ligne' => $ligne['numero'], 'message' => $e->getMessage()];
            }

            return;
        }

        foreach ($lignes as $ligne) {
            $this->traiterVersement($affectation, $ligne);
        }
    }

    /** @param array{numero: int, donnees: array} $ligne */
    private function traiterVersement(BusAffectation $affectation, array $ligne): void
    {
        $donnees = $ligne['donnees'];

        try {
            $mois = $this->resoudreMois($affectation->trajet->school_id, $donnees);

            if ($mois === null) {
                throw new RuntimeException("Mois de paiement introuvable ou non reconnu.");
            }

            if ($affectation->versements()->whereNull('annule_le')->whereDate('mois', $mois->toDateString())->exists()) {
                $this->versementsIgnores++;

                return;
            }

            if (empty($donnees['montant']) || $donnees['montant'] <= 0) {
                throw new RuntimeException('Montant manquant ou nul.');
            }

            $this->paiementService->encaisser($affectation, [
                'mois' => $mois->toDateString(),
                'montant' => $donnees['montant'],
                'date_versement' => $donnees['date_versement'] ?? $mois->toDateString(),
                'mode' => self::normaliserMode($donnees['mode'] ?? null),
                'note' => $donnees['saisi_par'] ?? null ? 'Import — saisi par '.$donnees['saisi_par'] : 'Import de situation transport.',
            ], $this->encaissePar);

            $this->versementsCrees++;
        } catch (RuntimeException $e) {
            $this->erreurs[] = ['ligne' => $ligne['numero'], 'message' => $e->getMessage()];
        }
    }

    private function resoudreEleve(array $donnees): Eleve
    {
        if (! empty($donnees['matricule'])) {
            $eleve = Eleve::where('school_id', $this->schoolId)->where('matricule', $donnees['matricule'])->first();

            if ($eleve !== null) {
                return $eleve;
            }
        }

        if (empty($donnees['nom_complet'])) {
            throw new RuntimeException("Élève introuvable : ni matricule ni nom exploitable.");
        }

        $eleve = Eleve::where('school_id', $this->schoolId)
            ->whereRaw('LOWER(nom_complet) = ?', [mb_strtolower(trim($donnees['nom_complet']))])
            ->first();

        if ($eleve === null) {
            throw new RuntimeException("Aucun élève ne correspond à « {$donnees['nom_complet']} ».");
        }

        return $eleve;
    }

    private function resoudreTrajet(?string $libelle): BusTrajet
    {
        if ($libelle === null || trim($libelle) === '') {
            throw new RuntimeException('Bus (trajet) non renseigné.');
        }

        $this->trajets ??= BusTrajet::where('school_id', $this->schoolId)->get()
            ->keyBy(fn (BusTrajet $t) => self::cle($t->nom))
            ->all();

        $trajet = $this->trajets[self::cle($libelle)] ?? null;

        if ($trajet === null) {
            throw new RuntimeException("Aucun trajet ne correspond à « {$libelle} ».");
        }

        return $trajet;
    }

    /** Sans correspondance, l'arrêt reste simplement vide plutôt que de faire échouer la ligne — il est facultatif sur une affectation. */
    private function resoudreArret(BusTrajet $trajet, ?string $libelle): ?BusArret
    {
        if ($libelle === null || trim($libelle) === '') {
            return null;
        }

        $cle = self::cle($libelle);

        return BusArret::where('trajet_id', $trajet->id)->get()
            ->first(fn (BusArret $a) => self::cle($a->nom) === $cle || self::cle((string) $a->lieu_dit) === $cle);
    }

    /** Le nom du mois seul ne dit pas l'année : elle se déduit de l'année scolaire active de l'école (SEPT-DÉC → celle de son début, JAN-JUIL → celle de sa fin). */
    private function resoudreMois(int $schoolId, array $donnees): ?Carbon
    {
        $nomMois = $donnees['mois'] ?? null;

        if ($nomMois === null) {
            return null;
        }

        $numero = self::MOIS[mb_strtolower(self::cle($nomMois))] ?? null;

        if ($numero === null) {
            return null;
        }

        $annee = AnneeScolaire::where('school_id', $schoolId)->where('is_active', true)->first();

        if ($annee === null) {
            return null;
        }

        $anneeCivile = $numero >= Carbon::parse($annee->date_debut)->month
            ? Carbon::parse($annee->date_debut)->year
            : Carbon::parse($annee->date_fin)->year;

        return Carbon::create($anneeCivile, $numero, 1)->startOfMonth();
    }

    private static function normaliserOption(?string $valeur): string
    {
        $cle = self::cle($valeur ?? '');

        return match (true) {
            str_contains($cle, 'RETOURSIMPLE') => 'retour_simple',
            str_contains($cle, 'ALLERSIMPLE') => 'aller_simple',
            default => 'aller_retour',
        };
    }

    private static function normaliserMode(?string $valeur): string
    {
        return self::MODES[mb_strtolower(self::cle($valeur ?? ''))] ?? 'especes';
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

        return [
            'matricule' => isset($ligne['matricule']) ? (string) $ligne['matricule'] : null,
            'nom_complet' => isset($ligne['nom_complet']) ? self::texte($ligne['nom_complet']) : null,
            'trajet' => isset($ligne['trajet']) ? self::texte($ligne['trajet']) : null,
            'date_versement' => self::date($ligne['date_versement'] ?? null),
            'montant' => self::montant($ligne['montant'] ?? null),
            'saisi_par' => isset($ligne['saisi_par']) ? self::texte($ligne['saisi_par']) : null,
            'arret' => isset($ligne['arret']) ? self::texte($ligne['arret']) : null,
            'mois' => isset($ligne['mois']) ? self::texte($ligne['mois']) : null,
            'option' => isset($ligne['option']) ? self::texte($ligne['option']) : null,
            'mode' => isset($ligne['mode']) ? self::texte($ligne['mode']) : null,
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

    private static function date(mixed $valeur): ?string
    {
        if ($valeur === null) {
            return null;
        }

        if ($valeur instanceof \DateTimeInterface) {
            return Carbon::instance($valeur)->toDateString();
        }

        $texte = trim((string) $valeur);

        if (is_numeric($texte)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $texte))->toDateString();
            } catch (\Throwable) {
                return null;
            }
        }

        foreach (['!d/m/Y H:i:s', '!d/m/Y', '!Y-m-d H:i:s', '!Y-m-d', '!d-m-Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $texte)->toDateString();
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    private static function montant(mixed $valeur): ?int
    {
        if ($valeur === null) {
            return null;
        }

        $nombre = preg_replace('/[^\d-]/', '', str_replace(',', '.', (string) $valeur));

        return $nombre === '' || $nombre === '-' ? null : (int) round((float) $nombre);
    }

    private static function cle(?string $libelle): string
    {
        return preg_replace('/[^A-Z0-9]+/', '', mb_strtoupper(Str::ascii((string) $libelle))) ?? '';
    }
}
