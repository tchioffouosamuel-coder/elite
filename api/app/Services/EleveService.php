<?php

namespace App\Services;

use App\Imports\EleveImport;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\School;
use App\Models\SousSysteme;
use App\Models\Tuteur;
use App\Models\TuteurTelephone;
use App\Models\User;
use App\Repositories\EleveRepository;
use App\Services\ScolariteService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Closure;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class EleveService extends BaseService
{
    public function __construct(
        private readonly EleveRepository $repository,
        private readonly ScolariteService $scolarite,
    ) {}

    /** @param int|array<int> $schoolId */
    public function list(?User $user, int|array $schoolId, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->repository->paginateForSchool($user, $schoolId, $filters, $perPage);
    }

    /** @param int|array<int> $schoolId */
    public function rechercheGlobale(?User $user, int|array $schoolId, string $terme): Collection
    {
        return $this->repository->rechercheGlobale($schoolId, $user, $terme);
    }

    /** @param int|array<int> $schoolId */
    public function find(int|array $schoolId, int $id): Eleve
    {
        return $this->repository->query()->forSchool($schoolId)->with(['classe.niveau', 'school:id,name,code,type', 'tuteurs.telephones'])->findOrFail($id);
    }

    public function create(int $schoolId, array $attributes): Eleve
    {
        return $this->transaction(function () use ($schoolId, $attributes) {
            $tuteurs = $attributes['tuteurs'] ?? [];
            unset($attributes['tuteurs']);

            $attributes['matricule'] = ($attributes['matricule'] ?? null) ?: Eleve::genererMatricule($schoolId);

            $eleve = $this->repository->create([...$attributes, 'school_id' => $schoolId]);
            $this->syncTuteurs($eleve, $schoolId, $tuteurs);

            return $eleve->load('tuteurs.telephones');
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

            return $eleve->load('tuteurs.telephones');
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

    /** Décoder une image en tant que bitmap RVBA coûte largeur × hauteur × 4 octets à GD. */
    private const MAX_PIXELS_PHOTO = 40_000_000;

    /**
     * Recadre en carré (centre) et redimensionne en 600x600 JPEG, comme upload_photo.php dans _smapp.
     */
    public function updatePhoto(Eleve $eleve, UploadedFile $file): Eleve
    {
        $chemin = $file->getRealPath();

        // getimagesize() ne lit que l'en-tête (quelques Ko), sans décoder les
        // pixels : une photo de téléphone à très haute résolution (ex. 8000x6000,
        // sous la barre des 5 Mo une fois compressée) ferait dépasser le
        // memory_limit de PHP dans imagecreatefromstring() ci-dessous — une
        // erreur fatale non rattrapable par try/catch, contrairement à un
        // Throwable classique. On la rejette donc proprement avant, plutôt
        // que de planter la requête.
        $dimensions = @getimagesize($chemin);
        if ($dimensions === false) {
            throw new UnprocessableEntityHttpException('Image illisible : le fichier envoyé n\'est pas une photo valide.');
        }
        [$width, $height] = $dimensions;
        if ($width * $height > self::MAX_PIXELS_PHOTO) {
            throw new UnprocessableEntityHttpException('Photo trop grande (résolution excessive) : réduisez sa taille avant de l\'envoyer.');
        }

        // Marge de sécurité pour les photos de résolution normale mais
        // néanmoins volumineuses à décoder ; restaurée après coup.
        $limiteAnterieure = ini_set('memory_limit', '512M');

        try {
            // imagecreatefromstring() détecte le format à partir du contenu réel
            // (contrairement à imagecreatefromjpeg/png qui plantent avec une
            // TypeError non attrapée si le mime détecté ne correspond pas
            // vraiment aux octets du fichier) et retourne false proprement sur
            // un fichier illisible plutôt que de faire planter la requête.
            $source = @imagecreatefromstring(file_get_contents($chemin));
            if ($source === false) {
                throw new UnprocessableEntityHttpException('Image illisible : le fichier envoyé n\'est pas une photo valide.');
            }

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
        } finally {
            // @ : ini_set() émet un warning (converti en exception par Laravel)
            // s'il ne peut pas redescendre sous la mémoire déjà consommée par
            // le décodage ci-dessus. Sans conséquence : la limite ne compte que
            // pour la requête en cours, qui se termine juste après.
            if ($limiteAnterieure !== false) {
                @ini_set('memory_limit', $limiteAnterieure);
            }
        }

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
     * `$schoolId` en tableau (super admin sans X-School-Id, mode agrégé) répartit
     * chaque ligne dans son école d'après `categorie_ecole` plutôt que de
     * toutes les rattacher à une seule — cf. `importPourToutesLesEcoles()`.
     *
     * `$file` accepte aussi un chemin (import découpé en lots, cf.
     * `importerChunk()`, qui rejoue cette même méthode sur un fichier
     * temporaire bien plus petit que l'original).
     *
     * @param  int|array<int>  $schoolId
     * @param  UploadedFile|string  $file
     * @return array{imported: int, updated: int, ignored: int, failed: int, errors: array, classes_introuvables: array<string, int>, dettes: int, dettes_montant: int, dettes_ignorees: int}
     */
    public function importFromExcel(int|array $schoolId, UploadedFile|string $file, ?int $importePar = null, ?Closure $progress = null): array
    {
        if (is_array($schoolId)) {
            return $this->importPourToutesLesEcoles($schoolId, $file, $importePar, $progress);
        }

        $import = new EleveImport($schoolId, $this->scolarite, $importePar, $progress);
        Excel::import($import, $file);

        return [
            'imported' => $import->importedCount,
            'updated' => $import->updatedCount,
            'ignored' => $import->ignoredCount,
            'failed' => count($import->failures()),
            'errors' => $import->failures(),
            'classes_introuvables' => $import->classesIntrouvables,
            'dettes' => $import->dettesCount,
            'dettes_montant' => $import->dettesMontantTotal,
            'dettes_ignorees' => $import->dettesIgnoreesCount,
        ];
    }

    /**
     * Un même fichier, une passe par école du complexe : chaque ligne n'est
     * retenue que par l'école dont `categorie_ecole` porte le type (cf.
     * `EleveImport::concerneCetteEcole()`), si bien que les résultats de
     * chaque passe portent sur des lignes disjointes — sauf la validation
     * (nom, sexe...), qui s'applique à toute ligne quelle que soit l'école et
     * échouerait donc identiquement à chaque passe : on ne garde que les
     * échecs de la première pour ne pas les compter en double.
     *
     * Sans la colonne, une ligne appartiendrait à toutes les écoles à la fois
     * — createOrUpdate() la dupliquerait dans chacune. On refuse plutôt que de
     * deviner : le fichier est probablement destiné à une seule école dans ce
     * cas, à importer avec un school_id précis (X-School-Id).
     *
     * @param  list<int>  $schoolIds
     * @param  UploadedFile|string  $file
     * @return array{imported: int, updated: int, ignored: int, failed: int, errors: array, classes_introuvables: array<string, int>, dettes: int, dettes_montant: int, dettes_ignorees: int}
     */
    private function importPourToutesLesEcoles(array $schoolIds, UploadedFile|string $file, ?int $importePar, ?Closure $progress = null): array
    {
        if (! EleveImport::porteColonneCategorieEcole($file)) {
            throw new RuntimeException(
                "Ce fichier ne porte pas de colonne categorie_ecole : précisez l'établissement pour l'importer."
            );
        }

        $total = [
            'imported' => 0,
            'updated' => 0,
            'failed' => 0,
            'errors' => [],
            'classes_introuvables' => [],
            'dettes' => 0,
            'dettes_montant' => 0,
            'dettes_ignorees' => 0,
        ];
        $lignesCount = 0;

        foreach ($schoolIds as $index => $schoolId) {
            $import = new EleveImport($schoolId, $this->scolarite, $importePar, $progress);
            Excel::import($import, $file);

            $lignesCount = $import->lignesCount;

            $total['imported'] += $import->importedCount;
            $total['updated'] += $import->updatedCount;
            $total['dettes'] += $import->dettesCount;
            $total['dettes_montant'] += $import->dettesMontantTotal;
            $total['dettes_ignorees'] += $import->dettesIgnoreesCount;

            foreach ($import->classesIntrouvables as $libelle => $n) {
                $total['classes_introuvables'][$libelle] = ($total['classes_introuvables'][$libelle] ?? 0) + $n;
            }

            // Une seule passe suffit à connaître les échecs de validation : ils
            // ne dépendent pas de l'école, les compter à chaque passe les
            // tripleraient pour un complexe à trois écoles.
            if ($index === array_key_first($schoolIds)) {
                $total['failed'] = count($import->failures());
                $total['errors'] = $import->failures();
            }
        }

        // Ce qu'aucune des écoles n'a reconnu comme sien : ni créé, ni mis à
        // jour, ni en échec de validation.
        $total['ignored'] = max(0, $lignesCount - $total['imported'] - $total['updated'] - $total['failed']);

        return $total;
    }

    /**
     * Découpe le fichier de situation en petits lots avant l'import, pour un
     * gros effectif (500+ lignes) : traiter chaque ligne coûte plusieurs
     * requêtes en base (élève, jusqu'à trois tuteurs, dette antérieure), et
     * un import synchrone d'un seul tenant dépasse alors facilement le délai
     * d'exécution PHP ou du serveur web — sans qu'on puisse le changer sans
     * accès au serveur. Chaque lot est ensuite importé par sa propre requête
     * HTTP (cf. `importerChunk()`), en rejouant `EleveImport` inchangée sur
     * un fichier bien plus petit : aucun risque de dénaturer les données en
     * réinventant sa logique de lecture.
     *
     * @return int nombre de lots créés
     */
    public function preparerImportDecoupe(UploadedFile $file, string $token, int $tailleLot = 60): int
    {
        $lecteur = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file->getRealPath());
        $lecteur->setReadDataOnly(true);
        $feuille = $lecteur->load($file->getRealPath())->getSheet(0);

        // `formatData: false` : les dates restent leur numéro de série Excel
        // brut, exactement ce que lirait un import non découpé — EleveImport
        // sait déjà convertir cette valeur (cf. sa méthode `date()`).
        $lignes = $feuille->toArray(null, true, false, false);
        $entetes = array_shift($lignes) ?? [];

        $dossier = $this->dossierImportDecoupe($token);
        if (! is_dir($dossier)) {
            mkdir($dossier, 0755, true);
        }

        $lots = array_chunk($lignes, max(1, $tailleLot));

        foreach ($lots as $i => $lot) {
            $classeur = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $feuilleLot = $classeur->getActiveSheet();
            $feuilleLot->fromArray($entetes, null, 'A1');
            $feuilleLot->fromArray($lot, null, 'A2');
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($classeur))->save("{$dossier}/{$i}.xlsx");
            $classeur->disconnectWorksheets();
        }

        return count($lots);
    }

    /**
     * Importe un lot préparé par `preparerImportDecoupe()`. Le lot traité est
     * supprimé aussitôt, et le dossier avec lui une fois le dernier lot passé
     * — rien ne doit s'accumuler sur le disque au-delà de la durée de l'import.
     *
     * @param  int|array<int>  $schoolId
     * @return array{resultat: array, dernier: bool}
     */
    public function importerChunk(int|array $schoolId, string $token, int $index, ?int $importePar = null): array
    {
        $dossier = $this->dossierImportDecoupe($token);
        $chemin = "{$dossier}/{$index}.xlsx";

        if (! is_file($chemin)) {
            throw new RuntimeException("Ce lot est introuvable — il a peut-être déjà été traité, ou l'import a expiré.");
        }

        $resultat = $this->importFromExcel($schoolId, $chemin, $importePar);

        @unlink($chemin);
        $dernier = ! is_file("{$dossier}/" . ($index + 1) . '.xlsx');
        if ($dernier) {
            @rmdir($dossier);
        }

        return ['resultat' => $resultat, 'dernier' => $dernier];
    }

    private function dossierImportDecoupe(string $token): string
    {
        // Un UUID généré côté serveur (cf. EleveController::importPreparer) :
        // jamais de segment de chemin fourni par le client dans `$token`.
        return storage_path('app/private/imports-eleves/' . $token);
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
            ->when($classe, fn($q) => $q->where('eleves.classe_id', $classe->id))
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
            ->map(fn(array $e) => ['school' => $e['school'], 'sous_systemes' => array_values($e['sous_systemes'])])
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
     * Chaque ligne porte la liste nominative des élèves de cet âge : l'écran
     * la déplie à la demande, pour répondre au « lesquels ? » que la pyramide
     * appelle immédiatement. Le volume reste celui de l'effectif, quelques
     * milliers de lignes au plus — une requête par âge coûterait davantage.
     *
     * @param  list<int>  $schoolIds
     * @return list<array{age: string, annees: int, mois: int, garcons: int, filles: int, total: int, eleves: list<array<string, mixed>>}>
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

        // Copie prise AVANT l'agrégation : un builder Eloquent est mutable, et
        // `selectRaw()`/`groupBy()` ci-dessous modifient `$query` lui-même. La
        // liste nominative hériterait sinon du `COUNT(*)` et du regroupement,
        // et rendrait des lignes agrégées au lieu des élèves.
        $requeteNominative = clone $query;

        // Alias `age_mois_total`, pas `age` : `Eleve::getAgeAttribute()`
        // (calculé depuis `date_naissance`, absente de ce `selectRaw`)
        // intercepterait sinon l'accès à `->age` et renverrait toujours `null`.
        $lignes = $query
            ->selectRaw($this->expressionAgeMois().' as age_mois_total, eleves.sexe as sexe, COUNT(*) as total')
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
                'eleves' => [],
            ];

            if ($ligne->sexe === 'F') {
                $parAge[$moisTotal]['filles'] += (int) $ligne->total;
            } else {
                $parAge[$moisTotal]['garcons'] += (int) $ligne->total;
            }
            $parAge[$moisTotal]['total'] += (int) $ligne->total;
        }

        // Liste nominative, rattachée à la ligne d'âge correspondante. La même
        // borne de vraisemblance qu'au comptage : un élève écarté du total ne
        // doit pas réapparaître dans le détail.
        $eleves = $requeteNominative
            ->with('classe:id,nom')
            ->selectRaw('eleves.*, '.$this->expressionAgeMois().' as age_mois_total')
            ->orderBy('eleves.nom_complet')
            ->get();

        foreach ($eleves as $eleve) {
            $moisTotal = (int) $eleve->age_mois_total;

            if (! isset($parAge[$moisTotal])) {
                continue;
            }

            $parAge[$moisTotal]['eleves'][] = [
                'id' => $eleve->id,
                'matricule' => $eleve->matricule,
                'nom_complet' => $eleve->nom_complet,
                'sexe' => $eleve->sexe,
                'classe' => $eleve->classe?->nom,
                'date_naissance' => $eleve->date_naissance?->format('Y-m-d'),
            ];
        }

        ksort($parAge);

        return array_values($parAge);
    }

    /**
     * Effectifs par minorité (Bororo, Baka, Déplacés internes), façon
     * tableau N°5 du rapport de rentrée MINEDUB — une ligne Garçons/Filles/
     * Total par catégorie, une école du périmètre à la fois.
     *
     * @param  list<int>  $schoolIds
     * @return array{bororo: array, baka: array, deplaces_internes: array, total: array}
     */
    public function rapportMinorites(array $schoolIds): array
    {
        $vide = fn () => ['garcons' => 0, 'filles' => 0, 'total' => 0];
        $resultat = ['bororo' => $vide(), 'baka' => $vide(), 'deplaces_internes' => $vide(), 'total' => $vide()];

        $lignes = Eleve::query()
            ->whereIn('eleves.school_id', $schoolIds)
            ->where('eleves.statut', 'actif')
            ->where(function ($q) {
                $q->where('bororo', 'Oui')->orWhere('baka', 'Oui')->orWhere('deplace_interne', 'Oui');
            })
            ->get(['sexe', 'bororo', 'baka', 'deplace_interne']);

        foreach ($lignes as $eleve) {
            $cle = $eleve->sexe === 'F' ? 'filles' : 'garcons';

            foreach (['bororo' => 'bororo', 'baka' => 'baka', 'deplaces_internes' => 'deplace_interne'] as $categorie => $champ) {
                if ($eleve->{$champ} === 'Oui') {
                    $resultat[$categorie][$cle]++;
                    $resultat[$categorie]['total']++;
                    $resultat['total'][$cle]++;
                    $resultat['total']['total']++;
                }
            }
        }

        return $resultat;
    }

    /**
     * Effectifs désagrégés par classe et par sexe (tableaux 1, 3, 4, 6 et 8
     * du rapport de rentrée MINEDUB) : le canevas les groupe par « cours »
     * (SIL, CP, CE1…) quand plusieurs classes parallèles couvrent le même
     * niveau, mais une ligne par classe reste la donnée exacte — c'est elle
     * qui garantit que la somme retombe toujours juste, groupement ou pas.
     *
     * @param  list<int>  $schoolIds
     * @return list<array{classe: array, garcons: array, filles: array, total: array}>
     */
    public function effectifsDesagregesParClasse(array $schoolIds): array
    {
        $classes = Classe::forSchool($schoolIds)->orderBy('nom')->get()->keyBy('id');

        $lignes = Eleve::query()
            ->whereIn('eleves.school_id', $schoolIds)
            ->where('eleves.statut', 'actif')
            ->whereNotNull('eleves.classe_id')
            ->selectRaw("
                eleves.classe_id as classe_id, eleves.sexe as sexe,
                COUNT(*) as total,
                SUM(CASE WHEN LOWER(eleves.nationalite) LIKE '%camerounais%' THEN 1 ELSE 0 END) as camerounais,
                SUM(CASE WHEN eleves.refugie = 'Oui' THEN 1 ELSE 0 END) as refugies,
                SUM(CASE WHEN eleves.redoublant = 1 THEN 1 ELSE 0 END) as redoublants,
                SUM(CASE WHEN eleves.numero_acte_naissance IS NULL OR eleves.numero_acte_naissance = '' THEN 1 ELSE 0 END) as sans_acte_naissance
            ")
            ->groupBy('eleves.classe_id', 'eleves.sexe')
            ->get();

        $vide = fn () => ['total' => 0, 'camerounais' => 0, 'non_camerounais' => 0, 'refugies' => 0, 'redoublants' => 0, 'sans_acte_naissance' => 0];

        $parClasse = [];
        foreach ($classes as $classe) {
            $parClasse[$classe->id] = [
                'classe' => ['id' => $classe->id, 'nom' => $classe->nom],
                'garcons' => $vide(),
                'filles' => $vide(),
                'total' => $vide(),
            ];
        }

        foreach ($lignes as $ligne) {
            if (! isset($parClasse[$ligne->classe_id])) {
                continue;
            }

            $valeurs = [
                'total' => (int) $ligne->total,
                'camerounais' => (int) $ligne->camerounais,
                'non_camerounais' => (int) $ligne->total - (int) $ligne->camerounais,
                'refugies' => (int) $ligne->refugies,
                'redoublants' => (int) $ligne->redoublants,
                'sans_acte_naissance' => (int) $ligne->sans_acte_naissance,
            ];

            $cle = $ligne->sexe === 'F' ? 'filles' : 'garcons';
            $parClasse[$ligne->classe_id][$cle] = $valeurs;
            foreach ($valeurs as $champ => $v) {
                $parClasse[$ligne->classe_id]['total'][$champ] += $v;
            }
        }

        return array_values($parClasse);
    }

    /**
     * Âge en mois complets à ce jour, exprimé dans le dialecte SQL de la
     * connexion active. `TIMESTAMPDIFF`/`CURDATE()` n'existent qu'en MySQL
     * (la production) ; l'équivalent SQLite ne sert qu'aux tests, qui n'ont
     * pas besoin de la même précision au jour près.
     */
    private function expressionAgeMois(): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return "((CAST(strftime('%Y','now') AS INTEGER) - CAST(strftime('%Y', eleves.date_naissance) AS INTEGER)) * 12"
                ." + (CAST(strftime('%m','now') AS INTEGER) - CAST(strftime('%m', eleves.date_naissance) AS INTEGER)))";
        }

        return 'TIMESTAMPDIFF(MONTH, eleves.date_naissance, CURDATE())';
    }

    private function syncTuteurs(Eleve $eleve, int $schoolId, array $tuteurs): void
    {
        $eleve->tuteurs()->detach();

        foreach ($tuteurs as $data) {
            $tuteur = $this->resolveTuteur($schoolId, $data);
            $this->syncTelephonesTuteur($tuteur, $data);

            $eleve->tuteurs()->attach($tuteur->id, [
                'lien_parente' => $data['lien_parente'] ?? null,
                'is_principal' => $data['is_principal'] ?? false,
            ]);
        }
    }

    /**
     * Retrouve le tuteur choisi via l'autocomplétion (`tuteur_id`, envoyé
     * uniquement quand l'utilisateur a cliqué une suggestion) et met à jour
     * sa fiche avec la saisie du formulaire ; à défaut, retombe sur l'ancien
     * comportement — dédoublonnage par numéro de téléphone, ou création
     * d'une fiche neuve — pour les saisies au clavier sans sélection.
     */
    private function resolveTuteur(int $schoolId, array $data): Tuteur
    {
        $baseAttributes = [
            'nom_complet' => $data['nom_complet'],
            'email' => $data['email'] ?? null,
            'profession' => $data['profession'] ?? null,
            'adresse' => $data['adresse'] ?? null,
        ];

        if (! empty($data['tuteur_id'])) {
            $tuteur = Tuteur::forSchool($schoolId)->find($data['tuteur_id']);
            if ($tuteur) {
                $tuteur->fill($baseAttributes)->save();

                return $tuteur;
            }
        }

        $telephonePrincipal = $this->extrairePrincipal($data);

        return $telephonePrincipal
            ? Tuteur::firstOrCreate(['school_id' => $schoolId, 'telephone' => $telephonePrincipal], $baseAttributes)
            : Tuteur::create([...$baseAttributes, 'school_id' => $schoolId]);
    }

    /** Le numéro flaggé `is_principal` dans `telephones`, sinon le premier, sinon l'ancien champ `telephone` unique. */
    private function extrairePrincipal(array $data): ?string
    {
        $telephones = $data['telephones'] ?? [];

        if ($telephones !== []) {
            $principal = collect($telephones)->first(fn ($tel) => ! empty($tel['is_principal'])) ?? $telephones[0];

            return $principal['numero'] ?? null;
        }

        return $data['telephone'] ?? null;
    }

    /**
     * Remplace intégralement les numéros du tuteur par ceux du formulaire
     * (au moins 3, un seul principal) et recopie le principal dans l'ancien
     * champ `tuteurs.telephone` — encore lu par la recherche rapide, les SMS
     * et la connexion au portail parent.
     */
    private function syncTelephonesTuteur(Tuteur $tuteur, array $data): void
    {
        $telephones = $data['telephones'] ?? [];

        if ($telephones === []) {
            if (! empty($data['telephone']) && $tuteur->telephones()->doesntExist()) {
                TuteurTelephone::create(['tuteur_id' => $tuteur->id, 'numero' => $data['telephone'], 'is_principal' => true]);
            }

            return;
        }

        $tuteur->telephones()->delete();

        $aUnPrincipal = collect($telephones)->contains(fn ($tel) => ! empty($tel['is_principal']));

        foreach ($telephones as $index => $tel) {
            TuteurTelephone::create([
                'tuteur_id' => $tuteur->id,
                'numero' => $tel['numero'],
                'is_principal' => $aUnPrincipal ? ! empty($tel['is_principal']) : $index === 0,
            ]);
        }

        $tuteur->forceFill(['telephone' => $this->extrairePrincipal($data)])->save();
    }
}
