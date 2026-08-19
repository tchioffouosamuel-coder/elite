<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\MatiereExport;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreMatiereRequest;
use App\Http\Resources\Api\V1\MatiereResource;
use App\Imports\MatiereImport;
use App\Models\Matiere;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MatiereController extends Controller
{
    public function index(): JsonResponse
    {
        $matieres = Matiere::forSchool(Tenant::schoolIds())
            ->with(['departement', 'school:id,name,code,type'])
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
            ->sortBy(fn ($a) => $a->classe?->nom)
            ->values();

        return ApiResponse::success($affectations->map(fn ($a) => [
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
        $matiere->load('school:id,name,code,type');

        return ApiResponse::created(new MatiereResource($matiere), 'Matière créée.');
    }

    public function update(StoreMatiereRequest $request, int $id): JsonResponse
    {
        $matiere = Matiere::forSchool(Tenant::schoolIds())->findOrFail($id);
        $data = $request->validated();
        unset($data['school_id']);
        $matiere->update($data);
        $matiere->load('school:id,name,code,type');

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

        return ApiResponse::success(message: count($ids).' matière(s) supprimée(s).');
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
        ]);

        $import = new MatiereImport(Tenant::schoolId(), $request->string('cycle')->toString());
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
    public function export(): BinaryFileResponse
    {
        return Excel::download(new MatiereExport(Tenant::schoolIds()), 'matieres.xlsx');
    }

    private function messageImport(MatiereImport $import): string
    {
        $parties = ["{$import->importedCount} matière(s) importée(s)"];

        if ($import->updatedCount > 0) {
            $parties[] = "{$import->updatedCount} mise(s) à jour";
        }

        if ($import->affectationsCount > 0) {
            $parties[] = "{$import->affectationsCount} affectation(s) rattachée(s)";
        }

        return implode(', ', $parties).'.';
    }
}
