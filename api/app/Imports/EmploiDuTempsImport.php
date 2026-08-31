<?php

namespace App\Imports;

use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\EmploiDuTemps;
use App\Models\Matiere;
use App\Models\Personnel;
use App\Services\EmploiDuTempsService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * Import de l'emploi du temps d'UNE classe — le même fichier que produit
 * {@see \App\Exports\EmploiDuTempsExport}, corrigé dans un tableur puis
 * réimporté tel quel : c'est le seul moyen de charger une grille complète
 * sans ressaisir chaque créneau à la main dans le formulaire.
 *
 * La matière doit déjà exister au catalogue de l'école (une ligne d'emploi du
 * temps ne crée pas de matière), mais son affectation à la classe
 * (`ClasseMatiere`) est créée à la volée si elle manque encore — même
 * tolérance que `EmploiDuTempsService::copierVers()`, qui affecte déjà la
 * matière sans enseignant plutôt que d'échouer la copie.
 *
 * Chaque ligne passe par les mêmes garde-fous que la saisie manuelle
 * (chevauchement, quota horaire hebdomadaire) : une ligne qui les enfreint est
 * ignorée plutôt que d'écraser un créneau existant ou de dépasser le quota
 * réglé pour la matière.
 */
class EmploiDuTempsImport implements SkipsEmptyRows, ToCollection, WithHeadingRow
{
    private const COLONNES = [
        'jour' => 'jour',
        'day' => 'jour',
        'heuredebut' => 'heure_debut',
        'heurededebut' => 'heure_debut',
        'debut' => 'heure_debut',
        'starttime' => 'heure_debut',
        'heurefin' => 'heure_fin',
        'heuredefin' => 'heure_fin',
        'fin' => 'heure_fin',
        'endtime' => 'heure_fin',
        'matiere' => 'matiere',
        'subject' => 'matiere',
        'enseignant' => 'enseignant',
        'teacher' => 'enseignant',
        'salle' => 'salle',
        'room' => 'salle',
        'classesassociees' => 'classes_associees',
        'classesassociees_' => 'classes_associees',
        'classesassociees(troncmun)' => 'classes_associees',
        'troncmun' => 'classes_associees',
    ];

    /** Mêmes séparateurs que `MatiereImport` — le tiret en est absent, les noms de classes en portent. */
    private const SEPARATEURS = [';', '|', "\n", "\r"];

    private const JOURS = [
        'LUNDI' => 1, 'MARDI' => 2, 'MERCREDI' => 3, 'JEUDI' => 4, 'VENDREDI' => 5, 'SAMEDI' => 6, 'DIMANCHE' => 7,
        'MONDAY' => 1, 'TUESDAY' => 2, 'WEDNESDAY' => 3, 'THURSDAY' => 4, 'FRIDAY' => 5, 'SATURDAY' => 6, 'SUNDAY' => 7,
    ];

    /** Libellés français, pour les messages d'erreur — {@see jourLibelle()}. */
    private const LIBELLES_JOURS = [
        1 => 'lundi', 2 => 'mardi', 3 => 'mercredi', 4 => 'jeudi', 5 => 'vendredi', 6 => 'samedi', 7 => 'dimanche',
    ];

    public int $importedCount = 0;

    public int $ignoredCount = 0;

    /** @var array<int, string> lignes en échec métier (chevauchement, quota, jour/heure invalides) */
    public array $erreurs = [];

    /** @var array<string, int> libellé de matière non rattachée au catalogue => nombre de lignes concernées */
    public array $matieresIntrouvables = [];

    /** @var array<string, int> */
    public array $enseignantsIntrouvables = [];

    /** @var array<string, int> libellé de classe associée non résolu => nombre de lignes concernées */
    public array $classesIntrouvables = [];

    /** @var array<string, int>|null clé normalisée du nom => id de matière */
    private ?array $matieres = null;

    /** @var array<string, int>|null clé normalisée du nom complet => id de personnel */
    private ?array $personnels = null;

    /** @var array<string, int>|null clé normalisée (nom ou sigle) => id de classe */
    private ?array $classes = null;

    public function __construct(
        private readonly Classe $classe,
        private readonly EmploiDuTempsService $service,
    ) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $ligne = $this->canoniser($row instanceof Collection ? $row->all() : (array) $row);

            $nomMatiere = trim((string) ($ligne['matiere'] ?? ''));
            if ($nomMatiere === '') {
                $this->ignoredCount++;

                continue;
            }

            $jour = $this->jour($ligne['jour'] ?? null);
            $heureDebut = $this->heure($ligne['heure_debut'] ?? null);
            $heureFin = $this->heure($ligne['heure_fin'] ?? null);

            if ($jour === null || $heureDebut === null || $heureFin === null || $heureFin <= $heureDebut) {
                $this->erreurs[] = "{$nomMatiere} : jour ou horaire invalide.";

                continue;
            }

            $matiereId = $this->matieres()[self::cle($nomMatiere)] ?? null;
            if ($matiereId === null) {
                $this->matieresIntrouvables[$nomMatiere] = ($this->matieresIntrouvables[$nomMatiere] ?? 0) + 1;

                continue;
            }

            $classeMatiere = ClasseMatiere::firstOrCreate(
                ['classe_id' => $this->classe->id, 'matiere_id' => $matiereId],
            );

            $enseignant = trim((string) ($ligne['enseignant'] ?? ''));
            if ($enseignant !== '') {
                $personnelId = $this->personnels()[self::cle($enseignant)] ?? null;

                if ($personnelId === null) {
                    $this->enseignantsIntrouvables[$enseignant] = ($this->enseignantsIntrouvables[$enseignant] ?? 0) + 1;
                } elseif ($classeMatiere->personnel_id === null) {
                    $classeMatiere->update(['personnel_id' => $personnelId]);
                }
            }

            $associees = $this->classesAssociees((string) ($ligne['classes_associees'] ?? ''));
            $salle = trim((string) ($ligne['salle'] ?? '')) ?: null;

            if ($this->service->chevauche($this->classe, $jour, $heureDebut, $heureFin, null, $associees)) {
                $this->erreurs[] = "{$nomMatiere} ({$this->jourLibelle($jour)} {$heureDebut}) : chevauche un créneau existant.";

                continue;
            }

            if ($erreurQuota = $this->service->depasseQuota($classeMatiere, $heureDebut, $heureFin)) {
                $this->erreurs[] = $erreurQuota;

                continue;
            }

            try {
                $creneau = EmploiDuTemps::create([
                    'school_id' => $this->classe->school_id,
                    'classe_id' => $this->classe->id,
                    'classe_matiere_id' => $classeMatiere->id,
                    'jour' => $jour,
                    'heure_debut' => $heureDebut,
                    'heure_fin' => $heureFin,
                    'salle' => $salle,
                ]);
                $creneau->classesAssociees()->sync($associees);

                $this->importedCount++;
            } catch (Throwable $e) {
                $this->erreurs[] = "{$nomMatiere} : {$e->getMessage()}";
            }
        }
    }

    /**
     * @param  array<string, mixed>  $ligne
     * @return array<string, mixed>
     */
    private function canoniser(array $ligne): array
    {
        $canonique = [];

        foreach ($ligne as $entete => $valeur) {
            $cle = self::COLONNES[$entete] ?? $entete;

            if (is_string($valeur)) {
                $valeur = trim($valeur);
            }

            if (! isset($canonique[$cle]) || $canonique[$cle] === null || $canonique[$cle] === '') {
                $canonique[$cle] = $valeur;
            }
        }

        return $canonique;
    }

    private function jour(mixed $valeur): ?int
    {
        if ($valeur === null || trim((string) $valeur) === '') {
            return null;
        }

        if (is_numeric($valeur)) {
            $jour = (int) $valeur;

            return $jour >= 1 && $jour <= 7 ? $jour : null;
        }

        return self::JOURS[self::cle((string) $valeur)] ?? null;
    }

    private function jourLibelle(int $jour): string
    {
        return self::LIBELLES_JOURS[$jour] ?? (string) $jour;
    }

    /**
     * `08:00` tel quel, ou le sérial Excel d'une cellule au format heure
     * (fraction de journée, ex. 0.333333 = 08:00) — même casse que les dates
     * Excel ailleurs dans ce codebase (cf. `DepenseImport::date()`).
     */
    private function heure(mixed $valeur): ?string
    {
        if ($valeur === null) {
            return null;
        }

        if ($valeur instanceof \DateTimeInterface) {
            return $valeur->format('H:i');
        }

        if (is_numeric($valeur) && (float) $valeur < 1) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $valeur)->format('H:i');
            } catch (Throwable) {
                return null;
            }
        }

        $texte = trim((string) $valeur);

        return preg_match('/^([01]?\d|2[0-3]):([0-5]\d)/', $texte, $m) ? "{$m[1]}:{$m[2]}" : null;
    }

    /** @return list<int> */
    private function classesAssociees(string $valeur): array
    {
        if (trim($valeur) === '') {
            return [];
        }

        $normalise = str_replace(self::SEPARATEURS, ';', $valeur);

        $ids = [];
        foreach (explode(';', $normalise) as $libelle) {
            $libelle = trim($libelle);
            if ($libelle === '') {
                continue;
            }

            $classeId = $this->classes()[self::cle($libelle)] ?? null;

            if ($classeId === null) {
                $this->classesIntrouvables[$libelle] = ($this->classesIntrouvables[$libelle] ?? 0) + 1;

                continue;
            }

            // La classe porteuse n'a pas à se rejoindre elle-même.
            if ($classeId !== $this->classe->id) {
                $ids[] = $classeId;
            }
        }

        return array_values(array_unique($ids));
    }

    /** @return array<string, int> */
    private function matieres(): array
    {
        if ($this->matieres !== null) {
            return $this->matieres;
        }

        $this->matieres = [];

        foreach (Matiere::where('school_id', $this->classe->school_id)->get(['id', 'nom']) as $matiere) {
            $cle = self::cle((string) $matiere->nom);

            if ($cle !== '' && ! isset($this->matieres[$cle])) {
                $this->matieres[$cle] = $matiere->id;
            }
        }

        return $this->matieres;
    }

    /** @return array<string, int> */
    private function personnels(): array
    {
        if ($this->personnels !== null) {
            return $this->personnels;
        }

        $this->personnels = [];

        foreach (Personnel::where('school_id', $this->classe->school_id)->get(['id', 'nom_complet']) as $personnel) {
            $cle = self::cle((string) $personnel->nom_complet);

            if ($cle !== '' && ! isset($this->personnels[$cle])) {
                $this->personnels[$cle] = $personnel->id;
            }
        }

        return $this->personnels;
    }

    /** @return array<string, int> */
    private function classes(): array
    {
        if ($this->classes !== null) {
            return $this->classes;
        }

        $this->classes = [];

        foreach (Classe::where('school_id', $this->classe->school_id)->get(['id', 'nom', 'sigle']) as $classe) {
            foreach ([$classe->nom, $classe->sigle] as $libelle) {
                $cle = self::cle((string) $libelle);

                if ($cle !== '' && ! isset($this->classes[$cle])) {
                    $this->classes[$cle] = $classe->id;
                }
            }
        }

        return $this->classes;
    }

    private static function cle(string $libelle): string
    {
        return preg_replace('/[^A-Z0-9]+/', '', mb_strtoupper(Str::ascii($libelle))) ?? '';
    }
}
