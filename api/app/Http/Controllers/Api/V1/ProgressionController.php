<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\ProgressionModeleClasseExport;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SaveProgressionRequest;
use App\Models\AnneeScolaire;
use App\Models\ChampPersonnalise;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\ProgressionColonne;
use App\Models\ProgressionItem;
use App\Models\Trimestre;
use App\Imports\ProgressionImport;
use App\Imports\ProgressionImportClasseAdapter;
use App\Services\ProgressionService;
use App\Support\Pdf\ProgressionFicheGenerator;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Programme d'enseignement annuel — modules, chapitres et leçons — et taux
 * d'avancement qui s'en déduit. Commun aux trois cycles : seule la façon de
 * découper les matières change, pas la nature du programme.
 */
class ProgressionController extends Controller
{
    public function __construct(private readonly ProgressionService $service) {}

    /** Programme d'une affectation classe↔matière, avec le cartouche de sa fiche. */
    public function show(int $classeMatiereId): JsonResponse
    {
        $classeMatiere = $this->affectation($classeMatiereId);

        return ApiResponse::success([
            'classe' => ['id' => $classeMatiere->classe->id, 'nom' => $classeMatiere->classe->nom],
            'matiere' => ['id' => $classeMatiere->matiere->id, 'nom' => $classeMatiere->matiere->nom],
            'items' => $this->service->arbre($classeMatiere),
            'colonnes' => $this->colonnesArray($classeMatiere),
            ...$this->cartouche($classeMatiere),
            ...$this->service->tauxAffectation($classeMatiere),
        ]);
    }

    public function save(SaveProgressionRequest $request, int $classeMatiereId): JsonResponse
    {
        $classeMatiere = $this->affectation($classeMatiereId);
        $compte = $this->service->remplacerArbre($classeMatiere, $request->input('items', []));

        return ApiResponse::success(
            ['items' => $this->service->arbre($classeMatiere), ...$this->service->tauxAffectation($classeMatiere)],
            "{$compte} élément(s) enregistré(s)."
        );
    }

    /**
     * Cartouche de la fiche secondaire — Specialty et Module/Competency,
     * saisis une fois par affectation. Le Department, lui, se déduit du
     * département de la matière et ne s'édite pas ici.
     */
    public function enregistrerCartouche(Request $request, int $classeMatiereId): JsonResponse
    {
        $classeMatiere = $this->affectation($classeMatiereId);

        $data = $request->validate([
            'module_competence' => ['nullable', 'string', 'max:255'],
            'specialite' => ['nullable', 'string', 'max:150'],
        ]);

        $classeMatiere->update($data);

        return ApiResponse::success($this->cartouche($classeMatiere), 'Cartouche de la fiche mis à jour.');
    }

    /**
     * Import de la fiche de progression au format du gabarit de
     * l'établissement (maternelle/primaire ou secondaire selon le cycle de
     * l'affectation visée).
     *
     * L'import complète sans écraser : une leçon déjà saisie à l'écran ne voit
     * remplir que ses champs restés vides (cf. ProgressionImport).
     */
    public function import(Request $request, int $classeMatiereId): JsonResponse
    {
        $request->validate([
            'fichier' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ]);

        $classeMatiere = $this->affectation($classeMatiereId);
        $fichier = $request->file('fichier');
        $cycle = ProgressionItem::cyclePour($classeMatiere->classe->school->type);

        $ligneReelle = ProgressionImport::ligneEnTete($fichier);
        $ligneAttendue = $cycle === 'secondaire' ? 8 : 7;

        if ($ligneReelle === null) {
            return ApiResponse::error("Ce fichier ne ressemble pas à une fiche de progression : la colonne « Week » est introuvable.", 422);
        }

        if ($ligneReelle !== $ligneAttendue) {
            $attendu = $cycle === 'secondaire' ? 'secondaire' : 'maternelle/primaire';
            $recu = $ligneReelle === 8 ? 'secondaire' : 'maternelle/primaire';

            return ApiResponse::error(
                "Cette matière est au format {$attendu}, mais le fichier importé est au format {$recu}.",
                422
            );
        }

        $import = new ProgressionImport($classeMatiere, $cycle);
        Excel::import($import, $fichier);

        $message = "{$import->creees} leçon(s) créée(s), {$import->completees} complétée(s)";
        $message .= $import->ignorees > 0 ? ", {$import->ignorees} ligne(s) ignorée(s)." : '.';

        return ApiResponse::success([
            'creees' => $import->creees,
            'completees' => $import->completees,
            'ignorees' => $import->ignorees,
            'items' => $this->service->arbre($classeMatiere),
            ...$this->service->tauxAffectation($classeMatiere),
        ], $message);
    }

    /**
     * Modèle Excel vide de la fiche de progression d'une classe entière — une
     * feuille par matière affectée, à remplir puis renvoyer en un seul fichier
     * via `importClasse()` plutôt que matière par matière.
     */
    public function modeleClasse(int $classeId): BinaryFileResponse
    {
        $classe = $this->classeAutorisee($classeId);
        $cycle = ProgressionItem::cyclePour($classe->school->type);

        $affectations = ClasseMatiere::where('classe_id', $classe->id)
            ->with(['matiere.departement', 'enseignant', 'classe.school', 'classe.titulaire'])
            ->when(
                request()->user()?->perimetre()->matieresRestreintesDans($classe->id),
                fn ($q) => $q->where('personnel_id', request()->user()->perimetre()->personnelId())
            )
            ->get()
            ->sortBy(fn (ClasseMatiere $cm) => $cm->matiere->nom)
            ->values();

        abort_if($affectations->isEmpty(), 404, "Aucune matière n'est affectée à cette classe.");

        $nomFichier = 'modele-progression-'.Str::slug($classe->nom).'.xlsx';

        return Excel::download(
            new ProgressionModeleClasseExport($affectations, $cycle, $this->anneeScolaireActive($classe->school_id)),
            $nomFichier
        );
    }

    /**
     * Import groupé de la fiche de progression d'une classe entière : un seul
     * classeur, une feuille par matière — chacune reconnue par l'id
     * d'affectation en préfixe de son titre (`"{id} Matière"`, posé par
     * `ProgressionModeleMatiereSheet::title()`), qu'elle ait été ou non
     * renommée, réordonnée ou amputée d'une partie de ses feuilles entre le
     * téléchargement du modèle et son renvoi.
     */
    public function importClasse(Request $request, int $classeId): JsonResponse
    {
        $request->validate([
            'fichier' => ['required', 'file', 'mimes:xlsx,xls', 'max:20480'],
        ]);

        $classe = $this->classeAutorisee($classeId);
        $cycle = ProgressionItem::cyclePour($classe->school->type);
        $fichier = $request->file('fichier');

        $lecteur = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($fichier->getRealPath());
        $lecteur->setReadDataOnly(true);
        $feuilles = $lecteur->listWorksheetInfo($fichier->getRealPath());

        $imports = [];
        $ignorees = [];

        foreach ($feuilles as $index => $info) {
            $titre = $info['worksheetName'];

            if (! preg_match('/^(\d+)\b/', $titre, $m)) {
                $ignorees[] = $titre;

                continue;
            }

            try {
                $classeMatiere = $this->affectation((int) $m[1]);
            } catch (\Throwable) {
                $ignorees[] = $titre;

                continue;
            }

            if ($classeMatiere->classe_id !== $classe->id) {
                $ignorees[] = $titre;

                continue;
            }

            $imports[$index] = new ProgressionImport($classeMatiere, $cycle);
        }

        if ($imports === []) {
            return ApiResponse::error(
                "Aucune feuille de ce fichier ne correspond à une matière de cette classe : téléchargez d'abord le modèle vide de la classe et remplissez-le sans modifier les titres des feuilles.",
                422
            );
        }

        Excel::import(new ProgressionImportClasseAdapter($imports), $fichier);

        $creees = array_sum(array_map(fn (ProgressionImport $i) => $i->creees, $imports));
        $completees = array_sum(array_map(fn (ProgressionImport $i) => $i->completees, $imports));

        $message = "{$creees} leçon(s) créée(s), {$completees} complétée(s) sur ".count($imports).' matière(s).';
        if ($ignorees !== []) {
            $message .= ' '.count($ignorees).' feuille(s) ignorée(s) : '.implode(', ', $ignorees).'.';
        }

        return ApiResponse::success([
            'creees' => $creees,
            'completees' => $completees,
            'matieres_importees' => count($imports),
            'feuilles_ignorees' => $ignorees,
        ], $message);
    }

    /** Fiche de progression en PDF, A4 paysage — un document par matière/classe. */
    public function pdf(Request $request, int $classeMatiereId)
    {
        $classeMatiere = $this->affectation($classeMatiereId);

        $trimestreId = $request->integer('trimestre_id') ?: null;

        $lecons = ProgressionItem::where('classe_matiere_id', $classeMatiere->id)
            ->where('type', 'lecon')
            ->with('sequence.trimestre')
            ->when($trimestreId, fn ($q, $id) => $q->whereHas('sequence', fn ($sq) => $sq->where('trimestre_id', $id)))
            ->orderBy('ordre')->orderBy('id')
            ->get();

        $colonnes = $classeMatiere->progressionColonnes;
        $cycle = ProgressionItem::cyclePour($classeMatiere->classe->school->type);

        $termeAffiche = $trimestreId
            ? $lecons->first()?->sequence?->trimestre?->libelle
            : null;

        $pdf = (new ProgressionFicheGenerator)->build(
            $classeMatiere->load(['classe.school', 'matiere.departement', 'enseignant']),
            $lecons,
            $colonnes,
            $cycle,
            $termeAffiche,
            $this->anneeScolaireActive($classeMatiere->classe->school_id),
        );

        $nomFichier = 'progression-'.\Illuminate\Support\Str::slug($classeMatiere->classe->nom.'-'.$classeMatiere->matiere->nom).'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nomFichier.'"',
        ]);
    }

    /**
     * Avancement de chaque matière d'une classe.
     *
     * L'appartenance de la classe au périmètre est déjà vérifiée par le
     * middleware `permission` (cf. VerifierPermission::classeConcernee), mais
     * pas le détail des matières : un enseignant qui partage la classe avec
     * des collègues ne doit voir que les siennes.
     */
    public function classe(Request $request, int $classeId): JsonResponse
    {
        $classe = Classe::forSchool(Tenant::schoolIds())->with('titulaire')->findOrFail($classeId);
        $perimetre = $request->user()->perimetre();

        $personnelId = $perimetre->matieresRestreintesDans($classeId) ? $perimetre->personnelId() : null;

        if ($trimestreId = $request->integer('trimestre_id') ?: null) {
            $trimestre = Trimestre::whereHas(
                'anneeScolaire',
                fn ($q) => $q->whereIn('school_id', Tenant::schoolIds())
            )->findOrFail($trimestreId);

            return ApiResponse::success($this->service->tauxClasseTrimestre($classe, $trimestre, $personnelId));
        }

        return ApiResponse::success($this->service->tauxClasse($classe, $personnelId));
    }

    /** Avancement de l'établissement, classe par classe — borné au périmètre du compte. */
    public function etablissement(Request $request): JsonResponse
    {
        $perimetre = $request->user()->perimetre();

        return ApiResponse::success($this->service->tauxEtablissement(
            Tenant::schoolIds(),
            $perimetre->classes(),
            fn (Classe $classe) => $perimetre->matieresRestreintesDans($classe->id) ? $perimetre->personnelId() : null,
        ));
    }

    /** Champs personnalisés définis pour une matière (tableaux d'informations spécifiques — module Ma journée). */
    public function champs(int $classeMatiereId): JsonResponse
    {
        $classeMatiere = $this->affectation($classeMatiereId);

        return ApiResponse::success(
            ChampPersonnalise::where('classe_matiere_id', $classeMatiere->id)
                ->orderBy('ordre')->orderBy('id')
                ->get(['id', 'libelle', 'type', 'ordre'])
        );
    }

    public function enregistrerChamps(Request $request, int $classeMatiereId): JsonResponse
    {
        $classeMatiere = $this->affectation($classeMatiereId);

        $data = $request->validate([
            'champs' => ['present', 'array'],
            'champs.*.id' => ['nullable', 'integer'],
            'champs.*.libelle' => ['required', 'string', 'max:100'],
            'champs.*.type' => ['required', Rule::in(ChampPersonnalise::TYPES)],
        ]);

        $conserves = [];

        foreach (array_values($data['champs']) as $ordre => $champ) {
            $attributs = [
                'classe_matiere_id' => $classeMatiere->id,
                'libelle' => $champ['libelle'],
                'type' => $champ['type'],
                'ordre' => $ordre + 1,
            ];

            $item = isset($champ['id'])
                ? ChampPersonnalise::where('classe_matiere_id', $classeMatiere->id)->find($champ['id'])
                : null;

            $item = $item ? tap($item)->update($attributs) : ChampPersonnalise::create($attributs);
            $conserves[] = $item->id;
        }

        // Un champ retiré de l'éditeur disparaît ; les valeurs déjà saisies
        // dans les séances passées restent en base, simplement orphelines.
        ChampPersonnalise::where('classe_matiere_id', $classeMatiere->id)
            ->whereNotIn('id', $conserves ?: [0])
            ->delete();

        return ApiResponse::success(
            ChampPersonnalise::where('classe_matiere_id', $classeMatiere->id)
                ->orderBy('ordre')->orderBy('id')
                ->get(['id', 'libelle', 'type', 'ordre']),
            'Champs personnalisés enregistrés.'
        );
    }

    /** Colonnes libres de la fiche de progression — jusqu'à dix par matière/classe. */
    public function colonnes(int $classeMatiereId): JsonResponse
    {
        $classeMatiere = $this->affectation($classeMatiereId);

        return ApiResponse::success($this->colonnesArray($classeMatiere));
    }

    public function enregistrerColonnes(Request $request, int $classeMatiereId): JsonResponse
    {
        $classeMatiere = $this->affectation($classeMatiereId);

        $data = $request->validate([
            'colonnes' => ['present', 'array', 'max:'.ProgressionColonne::MAX_PAR_MATIERE],
            'colonnes.*.id' => ['nullable', 'integer'],
            'colonnes.*.libelle' => ['required', 'string', 'max:60'],
        ]);

        $conserves = [];

        foreach (array_values($data['colonnes']) as $ordre => $colonne) {
            $attributs = [
                'classe_matiere_id' => $classeMatiere->id,
                'libelle' => $colonne['libelle'],
                'ordre' => $ordre + 1,
            ];

            $item = isset($colonne['id'])
                ? ProgressionColonne::where('classe_matiere_id', $classeMatiere->id)->find($colonne['id'])
                : null;

            $item = $item ? tap($item)->update($attributs) : ProgressionColonne::create($attributs);
            $conserves[] = $item->id;
        }

        // Une colonne retirée de l'éditeur disparaît ; ses valeurs déjà
        // saisies restent dans le JSON des leçons, simplement orphelines —
        // elles réapparaîtraient si la colonne était recréée avec le même id,
        // ce qui n'arrive jamais puisque l'id est auto-incrémenté.
        ProgressionColonne::where('classe_matiere_id', $classeMatiere->id)
            ->whereNotIn('id', $conserves ?: [0])
            ->delete();

        return ApiResponse::success($this->colonnesArray($classeMatiere->fresh()), 'Colonnes enregistrées.');
    }

    /** @return list<array{id: int, libelle: string, ordre: int}> */
    private function colonnesArray(ClasseMatiere $classeMatiere): array
    {
        return $classeMatiere->progressionColonnes->map(fn (ProgressionColonne $c) => [
            'id' => $c->id, 'libelle' => $c->libelle, 'ordre' => $c->ordre,
        ])->values()->all();
    }

    /**
     * Cycle et cartouche de la fiche : Department (déduit), Specialty et
     * Module/Competency (saisis), propres au secondaire — le primaire n'en a
     * pas dans son gabarit.
     *
     * @return array{cycle: string, departement: ?string, specialite: ?string, module_competence: ?string}
     */
    private function cartouche(ClasseMatiere $classeMatiere): array
    {
        $classeMatiere->loadMissing('matiere.departement');

        return [
            'cycle' => ProgressionItem::cyclePour($classeMatiere->classe->school->type),
            'departement' => $classeMatiere->matiere->departement?->nom,
            'specialite' => $classeMatiere->specialite,
            'module_competence' => $classeMatiere->module_competence,
        ];
    }

    private function anneeScolaireActive(int $schoolId): ?string
    {
        return AnneeScolaire::where('school_id', $schoolId)->where('is_active', true)->value('libelle');
    }

    /** Classe bornée au tenant et au périmètre — pour les deux actions d'import/modèle groupés. */
    private function classeAutorisee(int $classeId): Classe
    {
        $classe = Classe::forSchool(Tenant::schoolIds())->with('school')->findOrFail($classeId);

        $perimetre = request()->user()?->perimetre();

        if ($perimetre) {
            abort_unless($perimetre->couvre($classe->id), 403, "Cette classe n'entre pas dans votre périmètre.");
        }

        return $classe;
    }

    /**
     * L'id de l'affectation ne porte pas la classe dans l'URL : le middleware
     * `permission` ne peut donc pas la border comme il le fait pour les routes
     * `{classeId}`. Il faut le refaire ici, à la main, sans quoi un enseignant
     * verrait — et éditerait — la progression de matières qui ne lui ont
     * jamais été confiées, simplement en devinant un identifiant.
     */
    private function affectation(int $id): ClasseMatiere
    {
        $classeMatiere = ClasseMatiere::forSchool(Tenant::schoolIds())
            ->with(['classe.school', 'matiere'])
            ->findOrFail($id);

        $perimetre = request()->user()?->perimetre();

        if ($perimetre) {
            abort_unless(
                $perimetre->couvre($classeMatiere->classe_id),
                403,
                "Cette classe n'entre pas dans votre périmètre : vous n'y enseignez pas et elle ne vous a pas été confiée.",
            );

            if ($perimetre->matieresRestreintesDans($classeMatiere->classe_id)) {
                abort_unless(
                    $classeMatiere->personnel_id === $perimetre->personnelId(),
                    403,
                    "Cette matière n'entre pas dans votre périmètre : elle ne vous a pas été confiée.",
                );
            }
        }

        return $classeMatiere;
    }
}
