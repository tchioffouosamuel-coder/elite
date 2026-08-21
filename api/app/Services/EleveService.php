<?php

namespace App\Services;

use App\Imports\EleveImport;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\School;
use App\Models\SousSysteme;
use App\Models\Tuteur;
use App\Models\User;
use App\Repositories\EleveRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class EleveService extends BaseService
{
    public function __construct(private readonly EleveRepository $repository) {}

    /** @param int|array<int> $schoolId */
    public function list(?User $user, int|array $schoolId, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->repository->paginateForSchool($user, $schoolId, $filters, $perPage);
    }

    /** @param int|array<int> $schoolId */
    public function find(int|array $schoolId, int $id): Eleve
    {
        return $this->repository->query()->forSchool($schoolId)->with(['classe.niveau', 'school:id,name,code,type', 'tuteurs'])->findOrFail($id);
    }

    public function create(int $schoolId, array $attributes): Eleve
    {
        return $this->transaction(function () use ($schoolId, $attributes) {
            $tuteurs = $attributes['tuteurs'] ?? [];
            unset($attributes['tuteurs']);

            $attributes['matricule'] = ($attributes['matricule'] ?? null) ?: Eleve::genererMatricule($schoolId);

            $eleve = $this->repository->create([...$attributes, 'school_id' => $schoolId]);
            $this->syncTuteurs($eleve, $schoolId, $tuteurs);

            return $eleve->load('tuteurs');
        });
    }

    public function update(Eleve $eleve, array $attributes): Eleve
    {
        return $this->transaction(function () use ($eleve, $attributes) {
            $tuteurs = $attributes['tuteurs'] ?? null;
            unset($attributes['tuteurs']);

            $eleve = $this->repository->update($eleve, $attributes);

            if ($tuteurs !== null) {
                $this->syncTuteurs($eleve, $eleve->school_id, $tuteurs);
            }

            return $eleve->load('tuteurs');
        });
    }

    /**
     * Rattache l'élève à une classe d'une autre école du complexe. Notes,
     * absences et sanctions restent liées aux classe_matieres de l'école
     * d'origine : l'historique scolaire est conservé tel quel, seul le
     * rattachement courant change.
     */
    public function transferer(Eleve $eleve, Classe $classe): Eleve
    {
        return $this->repository->update($eleve, [
            'school_id' => $classe->school_id,
            'classe_id' => $classe->id,
            'statut' => 'actif',
        ]);
    }

    /**
     * Recadre en carré (centre) et redimensionne en 600x600 JPEG, comme upload_photo.php dans _smapp.
     */
    public function updatePhoto(Eleve $eleve, UploadedFile $file): Eleve
    {
        $source = $file->getMimeType() === 'image/png'
            ? imagecreatefrompng($file->getRealPath())
            : imagecreatefromjpeg($file->getRealPath());

        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);
        $srcX = intdiv($width - $side, 2);
        $srcY = intdiv($height - $side, 2);

        $square = imagecreatetruecolor(600, 600);
        imagecopyresampled($square, $source, 0, 0, $srcX, $srcY, 600, 600, $side, $side);
        imagedestroy($source);

        ob_start();
        imagejpeg($square, null, 90);
        $contents = ob_get_clean();
        imagedestroy($square);

        $path = 'eleves/photos/' . $eleve->id . '.jpg';
        Storage::disk('public')->put($path, $contents);

        return $this->repository->update($eleve, ['photo_path' => $path]);
    }

    /**
     * @param  int|array<int>  $schoolId
     * @return array{par_classe: array, par_genre: array, total: int}
     */
    public function repartition(int|array $schoolId): array
    {
        $parClasse = Eleve::forSchool($schoolId)
            ->selectRaw('classe_id, count(*) as total')
            ->groupBy('classe_id')
            ->with('classe:id,nom')
            ->get()
            ->map(fn($row) => ['classe' => $row->classe?->nom ?? 'Non affecté', 'total' => $row->total]);

        $parGenre = Eleve::forSchool($schoolId)
            ->selectRaw('sexe, count(*) as total')
            ->groupBy('sexe')
            ->pluck('total', 'sexe');

        return [
            'par_classe' => $parClasse,
            'par_genre' => ['garcons' => $parGenre['M'] ?? 0, 'filles' => $parGenre['F'] ?? 0],
            'total' => Eleve::forSchool($schoolId)->count(),
        ];
    }

    /**
     * `ignored` compte les lignes d'une autre école du complexe (le fichier de
     * situation couvre maternelle, primaire et secondaire d'un seul tenant) et
     * `classes_introuvables` les libellés de classe non rattachés : sans ce
     * retour, l'utilisateur ne verrait qu'un total d'import inexpliqué.
     *
     * @return array{imported: int, updated: int, ignored: int, failed: int, errors: array, classes_introuvables: array<string, int>}
     */
    public function importFromExcel(int $schoolId, UploadedFile $file): array
    {
        $import = new EleveImport($schoolId);
        Excel::import($import, $file);

        return [
            'imported' => $import->importedCount,
            'updated' => $import->updatedCount,
            'ignored' => $import->ignoredCount,
            'failed' => count($import->failures()),
            'errors' => $import->failures(),
            'classes_introuvables' => $import->classesIntrouvables,
        ];
    }

    public function delete(Eleve $eleve): void
    {
        $this->transaction(function () use ($eleve) {
            // Détacher les tuteurs
            $eleve->tuteurs()->detach();

            // Supprimer la photo si elle existe
            if ($eleve->photo_path) {
                Storage::disk('public')->delete($eleve->photo_path);
            }

            // Supprimer l'élève
            $this->repository->delete($eleve);
        });
    }

    /** @return array{nouveaux: int, redoublants: int, camerounais: int, refugies: int, effectif: int} */
    private function ligneEffectifsVide(): array
    {
        return ['nouveaux' => 0, 'redoublants' => 0, 'camerounais' => 0, 'refugies' => 0, 'effectif' => 0];
    }

    /** Quatre colonnes indépendantes (pas une répartition croisée) : chaque élève actif compte dans chacune de celles qui le concernent. */
    private const SELECT_EFFECTIFS = "
        SUM(CASE WHEN eleves.redoublant = 0 THEN 1 ELSE 0 END) as nouveaux,
        SUM(CASE WHEN eleves.redoublant = 1 THEN 1 ELSE 0 END) as redoublants,
        SUM(CASE WHEN LOWER(eleves.nationalite) LIKE '%camerounais%' THEN 1 ELSE 0 END) as camerounais,
        SUM(CASE WHEN eleves.refugie = 'Oui' THEN 1 ELSE 0 END) as refugies,
        COUNT(*) as total
    ";

    /**
     * Récapitulatif d'effectifs façon rentrée scolaire : Garçons/Filles/Total
     * en lignes, Nouveaux/Redoublants/Camerounais/Réfugiés en colonnes — une
     * ligne par école du périmètre, jamais absente même sans élève, pour que
     * le tableau imprimé garde une forme stable d'une école à l'autre.
     *
     * @param  list<int>  $schoolIds
     * @return list<array{school: array, classe: ?array, garcons: array, filles: array, total: array}>
     */
    public function recapitulatifEffectifs(array $schoolIds, ?int $classeId = null): array
    {
        // Une classe appartient à une seule école : filtrer dessus réduit
        // naturellement le résultat à une seule carte, sans logique séparée.
        $classe = $classeId ? Classe::forSchool($schoolIds)->with('school')->findOrFail($classeId) : null;
        $ecoles = $classe
            ? collect([$classe->school])->keyBy('id')
            : School::whereIn('id', $schoolIds)->get()->keyBy('id');

        $lignes = Eleve::query()
            ->whereIn('eleves.school_id', $schoolIds)
            ->where('eleves.statut', 'actif')
            ->when($classe, fn ($q) => $q->where('eleves.classe_id', $classe->id))
            ->selectRaw('eleves.school_id as school_id, eleves.sexe as sexe, ' . self::SELECT_EFFECTIFS)
            ->groupBy('eleves.school_id', 'eleves.sexe')
            ->get();

        $parEcole = [];
        foreach ($ecoles as $ecole) {
            $parEcole[$ecole->id] = [
                'school' => ['id' => $ecole->id, 'name' => $ecole->name],
                'classe' => $classe ? ['id' => $classe->id, 'nom' => $classe->nom] : null,
                'garcons' => $this->ligneEffectifsVide(),
                'filles' => $this->ligneEffectifsVide(),
                'total' => $this->ligneEffectifsVide(),
            ];
        }

        foreach ($lignes as $ligne) {
            if (! isset($parEcole[$ligne->school_id])) {
                continue;
            }

            $valeurs = [
                'nouveaux' => (int) $ligne->nouveaux,
                'redoublants' => (int) $ligne->redoublants,
                'camerounais' => (int) $ligne->camerounais,
                'refugies' => (int) $ligne->refugies,
                'effectif' => (int) $ligne->total,
            ];

            $cle = $ligne->sexe === 'F' ? 'filles' : 'garcons';
            $parEcole[$ligne->school_id][$cle] = $valeurs;
            foreach ($valeurs as $champ => $v) {
                $parEcole[$ligne->school_id]['total'][$champ] += $v;
            }
        }

        return array_values($parEcole);
    }

    /**
     * Même récapitulatif, décomposé par sous-système au sein de chaque école.
     * L'élève n'a pas de sous-système propre : il hérite de celui de sa
     * classe (`classes.sous_systeme_id`), d'où la jointure — un élève sans
     * classe, ou dont la classe n'a pas de sous-système, tombe dans le
     * panier « Sans sous-système » plutôt que d'être compté deux fois ou
     * silencieusement omis.
     *
     * @param  list<int>  $schoolIds
     * @return list<array{school: array, sous_systemes: list<array{sous_systeme: ?array, garcons: array, filles: array, total: array}>}>
     */
    public function recapitulatifEffectifsParSousSysteme(array $schoolIds): array
    {
        $ecoles = School::whereIn('id', $schoolIds)->get()->keyBy('id');

        $lignes = Eleve::query()
            ->leftJoin('classes', 'classes.id', '=', 'eleves.classe_id')
            ->whereIn('eleves.school_id', $schoolIds)
            ->where('eleves.statut', 'actif')
            ->selectRaw('eleves.school_id as school_id, classes.sous_systeme_id as sous_systeme_id, eleves.sexe as sexe, ' . self::SELECT_EFFECTIFS)
            ->groupBy('eleves.school_id', 'classes.sous_systeme_id', 'eleves.sexe')
            ->get();

        $sousSystemes = SousSysteme::whereIn('id', $lignes->pluck('sous_systeme_id')->filter()->unique())->get()->keyBy('id');

        $parEcole = [];
        foreach ($lignes as $ligne) {
            $ecole = $ecoles->get($ligne->school_id);
            if (! $ecole) {
                continue;
            }

            $parEcole[$ligne->school_id] ??= ['school' => ['id' => $ecole->id, 'name' => $ecole->name], 'sous_systemes' => []];

            $cleSs = $ligne->sous_systeme_id ?? 0;
            $parEcole[$ligne->school_id]['sous_systemes'][$cleSs] ??= [
                'sous_systeme' => $ligne->sous_systeme_id
                    ? ['id' => $ligne->sous_systeme_id, 'nom' => $sousSystemes->get($ligne->sous_systeme_id)?->nom ?? '—']
                    : null,
                'garcons' => $this->ligneEffectifsVide(),
                'filles' => $this->ligneEffectifsVide(),
                'total' => $this->ligneEffectifsVide(),
            ];

            $valeurs = [
                'nouveaux' => (int) $ligne->nouveaux,
                'redoublants' => (int) $ligne->redoublants,
                'camerounais' => (int) $ligne->camerounais,
                'refugies' => (int) $ligne->refugies,
                'effectif' => (int) $ligne->total,
            ];

            $cleSexe = $ligne->sexe === 'F' ? 'filles' : 'garcons';
            $parEcole[$ligne->school_id]['sous_systemes'][$cleSs][$cleSexe] = $valeurs;
            foreach ($valeurs as $champ => $v) {
                $parEcole[$ligne->school_id]['sous_systemes'][$cleSs]['total'][$champ] += $v;
            }
        }

        return collect($parEcole)
            ->map(fn (array $e) => ['school' => $e['school'], 'sous_systemes' => array_values($e['sous_systemes'])])
            ->values()
            ->all();
    }

    /**
     * Pyramide des âges : effectif garçons/filles par âge exact (années.mois,
     * ex. « 1.2 » = 1 an 2 mois) plutôt qu'arrondi à l'année révolue — un
     * enfant de 2 ans 11 mois n'a pas le développement d'un enfant de 2 ans 1
     * mois, distinction qui compte en maternelle/primaire. Sert à la fois à
     * l'échelle d'une école (et, via `$sousSystemeId`, d'un sous-système) et
     * à celle d'une seule classe (onglet Élèves de la fiche classe) — d'où
     * les deux filtres optionnels plutôt que deux méthodes qui dupliqueraient
     * le calcul.
     *
     * @param  list<int>  $schoolIds
     * @return list<array{age: string, annees: int, mois: int, garcons: int, filles: int, total: int}>
     */
    public function tableauAges(array $schoolIds, ?int $sousSystemeId = null, ?int $classeId = null): array
    {
        $query = Eleve::query()
            ->whereIn('eleves.school_id', $schoolIds)
            ->where('eleves.statut', 'actif')
            ->whereNotNull('eleves.date_naissance');

        if ($classeId !== null) {
            $query->where('eleves.classe_id', $classeId);
        } elseif ($sousSystemeId !== null) {
            $query->join('classes', 'classes.id', '=', 'eleves.classe_id')
                ->where('classes.sous_systeme_id', $sousSystemeId);
        }

        // Alias `age_mois_total`, pas `age` : `Eleve::getAgeAttribute()`
        // (calculé depuis `date_naissance`, absente de ce `selectRaw`)
        // intercepterait sinon l'accès à `->age` et renverrait toujours `null`.
        $lignes = $query
            ->selectRaw('TIMESTAMPDIFF(MONTH, eleves.date_naissance, CURDATE()) as age_mois_total, eleves.sexe as sexe, COUNT(*) as total')
            ->groupBy('age_mois_total', 'eleves.sexe')
            ->get();

        $parAge = [];
        foreach ($lignes as $ligne) {
            $moisTotal = (int) $ligne->age_mois_total;

            // Une date de naissance corrompue (élève né « demain », ou il y a
            // deux siècles) produirait sinon une ligne aberrante isolée sur la
            // pyramide plutôt qu'une valeur plausible.
            if ($moisTotal < 0 || $moisTotal > 1200) {
                continue;
            }

            $annees = intdiv($moisTotal, 12);
            $mois = $moisTotal % 12;

            $parAge[$moisTotal] ??= [
                'age' => "{$annees}.{$mois}",
                'annees' => $annees,
                'mois' => $mois,
                'garcons' => 0,
                'filles' => 0,
                'total' => 0,
            ];

            if ($ligne->sexe === 'F') {
                $parAge[$moisTotal]['filles'] += (int) $ligne->total;
            } else {
                $parAge[$moisTotal]['garcons'] += (int) $ligne->total;
            }
            $parAge[$moisTotal]['total'] += (int) $ligne->total;
        }

        ksort($parAge);

        return array_values($parAge);
    }

    private function syncTuteurs(Eleve $eleve, int $schoolId, array $tuteurs): void
    {
        $eleve->tuteurs()->detach();

        foreach ($tuteurs as $data) {
            $baseAttributes = [
                'nom_complet' => $data['nom_complet'],
                'email' => $data['email'] ?? null,
                'profession' => $data['profession'] ?? null,
                'adresse' => $data['adresse'] ?? null,
            ];

            $tuteur = ! empty($data['telephone'])
                ? Tuteur::firstOrCreate(['school_id' => $schoolId, 'telephone' => $data['telephone']], $baseAttributes)
                : Tuteur::create([...$baseAttributes, 'school_id' => $schoolId]);

            $eleve->tuteurs()->attach($tuteur->id, [
                'lien_parente' => $data['lien_parente'] ?? null,
                'is_principal' => $data['is_principal'] ?? false,
            ]);
        }
    }
}
