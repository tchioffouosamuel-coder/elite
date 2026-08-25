<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\MatiereExport;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreMatiereRequest;
use App\Http\Resources\Api\V1\MatiereResource;
use App\Imports\MatiereImport;
use App\Models\Classe;
use App\Models\Matiere;
use App\Services\CompetenceAttributionService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MatiereController extends Controller
{
    public function __construct(private readonly CompetenceAttributionService $attribution) {}

    public function index(): JsonResponse
    {
        $matieres = Matiere::forSchool(Tenant::schoolIds())
            ->with(['departement', 'competence', 'school:id,name,code,type'])
            ->withCount('classeMatieres')
            ->orderBy('nom')
            ->get();

        return ApiResponse::success(MatiereResource::collection($matieres));
    }

    /** Classes où cette matière est enseignée — pour la modale « Enseignée dans X classe(s) ». */
    public function classes(int $id): JsonResponse
    {
        $matiere = Matiere::forSchool(Tenant::schoolIds())->findOrFail($id);

        $affectations = $matiere->classeMatieres()
            ->with(['classe', 'enseignant'])
            ->get()
            ->sortBy(fn($a) => $a->classe?->nom)
            ->values();

        return ApiResponse::success($affectations->map(fn($a) => [
            'classe_matiere_id' => $a->id,
            'classe' => $a->classe ? ['id' => $a->classe->id, 'nom' => $a->classe->nom] : null,
            'enseignant' => $a->enseignant ? ['id' => $a->enseignant->id, 'nom_complet' => $a->enseignant->nom_complet] : null,
            'coefficient' => $a->coefficient,
        ])->values());
    }

    public function store(StoreMatiereRequest $request): JsonResponse
    {
        $data = $request->validated();
        $schoolId = Tenant::resolveWriteSchoolId($data['school_id'] ?? null);
        unset($data['school_id']);

        $matiere = Matiere::create([...$data, 'school_id' => $schoolId])->refresh();

        // Une matière ajoutee a une competence deja attribuee doit rejoindre
        // les classes qui la portent : sans cela, seules les classes
        // attribuees ensuite la verraient, et il faudrait repasser sur chacune.
        $installees = $this->attribution->propagerMatiere($matiere);

        $matiere->load(['school:id,name,code,type', 'competence']);

        return ApiResponse::created(
            new MatiereResource($matiere),
            $installees > 0
                ? "Matière créée et installée dans {$installees} classe(s)."
                : 'Matière créée.',
        );
    }

    public function update(StoreMatiereRequest $request, int $id): JsonResponse
    {
        $matiere = Matiere::forSchool(Tenant::schoolIds())->findOrFail($id);
        $data = $request->validated();
        unset($data['school_id']);
        $matiere->update($data);

        // Rattachement a une competence apres coup : meme propagation qu'a la
        // creation, sinon la matiere resterait absente des classes concernees.
        $this->attribution->propagerMatiere($matiere->refresh());

        $matiere->load(['school:id,name,code,type', 'competence']);

        return ApiResponse::success(new MatiereResource($matiere), 'Matière mise à jour.');
    }

    public function destroy(int $id): JsonResponse
    {
        $matiere = Matiere::forSchool(Tenant::schoolIds())->findOrFail($id);
        $matiere->delete();

        return ApiResponse::success(message: 'Matière supprimée.');
    }

    public function batchDestroy(): JsonResponse
    {
        $ids = request()->input('ids', []);

        if (empty($ids)) {
            return ApiResponse::error('Aucune matière à supprimer.');
        }

        Matiere::forSchool(Tenant::schoolIds())->whereIn('id', $ids)->delete();

        return ApiResponse::success(message: count($ids) . ' matière(s) supprimée(s).');
    }

    /**
     * Import du catalogue des matières. Le cycle est déclaré par l'utilisateur
     * au moment du dépôt : le fichier d'un secondaire porte des départements
     * et des affectations, celui d'un primaire un barème par volet, et rien
     * dans le fichier lui-même ne permet de trancher à coup sûr.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
            'cycle' => ['required', Rule::in(MatiereImport::CYCLES)],
            'classe_id' => ['nullable', 'integer'],
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
        ]);

        // Comme `store()` : en mode agrégé (super admin, « Toutes les écoles »),
        // deviner l'établissement serait arbitraire — et c'était précisément le
        // bug qu'a connu la production, où l'import écrivait sous l'école
        // propre du compte plutôt que sous celle affichée, la laissant hors du
        // périmètre que la liste relit ensuite.
        $schoolId = Tenant::resolveWriteSchoolId($request->integer('school_id') ?: null);

        $classeId = $request->integer('classe_id') ?: null;
        if ($classeId !== null) {
            Classe::forSchool(Tenant::schoolIds())->findOrFail($classeId);
        }

        $cycle = $request->string('cycle')->toString();
        $import = new MatiereImport($schoolId, $cycle, $classeId);
        Excel::import($import, $request->file('file'));

        return ApiResponse::success([
            'imported' => $import->importedCount,
            'updated' => $import->updatedCount,
            'ignored' => $import->ignoredCount,
            'failed' => 0,
            'affectations' => $import->affectationsCount,
            // Libellés que l'import n'a pas su rattacher : l'utilisateur
            // corrige son fichier et rejoue, plutôt que de chercher ce qui
            // manque en comparant deux listes.
            'classes_introuvables' => $import->classesIntrouvables,
            'enseignants_introuvables' => $import->enseignantsIntrouvables,
        ], $this->messageImport($import));
    }

    /**
     * Export au format relu par l'import : c'est le même fichier qui sert de
     * sauvegarde, de gabarit de saisie et de support de correction en masse.
     */
    public function export(Request $request): BinaryFileResponse
    {
        $classeId = $request->integer('classe_id') ?: null;
        if ($classeId !== null) {
            Classe::forSchool(Tenant::schoolIds())->findOrFail($classeId);
        }

        return Excel::download(new MatiereExport(Tenant::schoolIds(), $classeId), 'matieres.xlsx');
    }

    /**
     * Le cycle ne change plus la nature de ce qui est importé (cf.
     * MatiereImport) : toujours des matières, quel que soit l'établissement.
     */
    private function messageImport(MatiereImport $import): string
    {
        $parties = ["{$import->importedCount} matière(s) importée(s)"];

        if ($import->updatedCount > 0) {
            $parties[] = "{$import->updatedCount} mise(s) à jour";
        }

        if ($import->affectationsCount > 0) {
            $parties[] = "{$import->affectationsCount} affectation(s) rattachée(s)";
        }

        return implode(', ', $parties) . '.';
    }
}
