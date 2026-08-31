<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\EmploiDuTempsExport;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Imports\EmploiDuTempsImport;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\EmploiDuTemps;
use App\Models\Trimestre;
use App\Services\EmploiDuTempsService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EmploiDuTempsController extends Controller
{
    public function __construct(private readonly EmploiDuTempsService $service) {}

    public function index(int $classeId): JsonResponse
    {
        $classe = $this->classe($classeId);

        return ApiResponse::success($this->service->grille($classe)->map(EmploiDuTempsService::presenter(...)));
    }

    public function store(Request $request, int $classeId): JsonResponse
    {
        $classe = $this->classe($classeId);
        $data = $this->valider($request, $classe);

        $associees = $data['classes_associees'];
        unset($data['classes_associees']);

        if ($this->service->chevauche($classe, $data['jour'], $data['heure_debut'], $data['heure_fin'], null, $associees)) {
            return ApiResponse::error("Ce créneau en chevauche un autre pour l'une des classes concernées.", 422);
        }

        $classeMatiere = ClasseMatiere::findOrFail($data['classe_matiere_id']);

        if ($erreurQuota = $this->service->depasseQuota($classeMatiere, $data['heure_debut'], $data['heure_fin'])) {
            return ApiResponse::error($erreurQuota, 422);
        }

        $creneau = EmploiDuTemps::create([...$data, 'classe_id' => $classe->id, 'school_id' => $classe->school_id]);
        $creneau->classesAssociees()->sync($associees);

        return ApiResponse::created(
            EmploiDuTempsService::presenter($creneau->load('classeMatiere.matiere', 'classesAssociees')),
            'Créneau ajouté.',
        );
    }

    public function update(Request $request, int $classeId, int $id): JsonResponse
    {
        $classe = $this->classe($classeId);
        $creneau = EmploiDuTemps::where('classe_id', $classe->id)->findOrFail($id);
        $data = $this->valider($request, $classe);

        $associees = $data['classes_associees'];
        unset($data['classes_associees']);

        if ($this->service->chevauche($classe, $data['jour'], $data['heure_debut'], $data['heure_fin'], $creneau->id, $associees)) {
            return ApiResponse::error("Ce créneau en chevauche un autre pour l'une des classes concernées.", 422);
        }

        $classeMatiere = ClasseMatiere::findOrFail($data['classe_matiere_id']);

        if ($erreurQuota = $this->service->depasseQuota($classeMatiere, $data['heure_debut'], $data['heure_fin'], $creneau->id)) {
            return ApiResponse::error($erreurQuota, 422);
        }

        $creneau->update($data);
        $creneau->classesAssociees()->sync($associees);

        return ApiResponse::success(
            EmploiDuTempsService::presenter($creneau->fresh(['classeMatiere.matiere', 'classesAssociees'])),
            'Créneau mis à jour.',
        );
    }

    /** Copie une sélection de créneaux vers une autre classe, sans reprendre l'enseignant. */
    public function copier(Request $request, int $classeId): JsonResponse
    {
        $classe = $this->classe($classeId);

        $data = $request->validate([
            'creneau_ids' => ['required', 'array', 'min:1'],
            'creneau_ids.*' => ['integer'],
            'classe_id' => ['required', 'integer'],
        ]);

        abort_if((int) $data['classe_id'] === $classe->id, 422, 'La classe de destination doit être différente de la classe source.');

        $classeCible = Classe::forSchool(Tenant::schoolIds())->findOrFail($data['classe_id']);

        $creneaux = EmploiDuTemps::where('classe_id', $classe->id)
            ->whereIn('id', $data['creneau_ids'])
            ->with('classeMatiere')
            ->get();

        if ($creneaux->isEmpty()) {
            return ApiResponse::notFound();
        }

        [$copies, $ignores] = $this->service->copierVers($creneaux, $classeCible);

        return ApiResponse::success(['copies' => $copies, 'ignores' => $ignores], "{$copies} créneau(x) copié(s).");
    }

    public function destroy(int $classeId, int $id): JsonResponse
    {
        $classe = $this->classe($classeId);
        EmploiDuTemps::where('classe_id', $classe->id)->findOrFail($id)->delete();

        return ApiResponse::success(message: 'Créneau supprimé.');
    }

    /** Matérialise les créneaux en séances datées sur une période. */
    public function genererSeances(Request $request, int $classeId): JsonResponse
    {
        $classe = $this->classe($classeId);

        $data = $request->validate([
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
            'trimestre_id' => ['nullable', 'integer'],
        ]);

        $trimestre = $data['trimestre_id'] ?? null
            ? Trimestre::whereHas('anneeScolaire', fn ($q) => $q->where('school_id', $classe->school_id))
                ->find($data['trimestre_id'])
            : null;

        $creees = $this->service->genererSeances(
            $classe,
            Carbon::parse($data['date_debut']),
            Carbon::parse($data['date_fin']),
            $trimestre,
        );

        return ApiResponse::success(['creees' => $creees], "{$creees} séance(s) générée(s).");
    }

    /**
     * Import de l'emploi du temps de cette classe, dans la forme produite par
     * {@see export()} : les créneaux déjà en place restent (aucune purge
     * préalable), une ligne qui en chevauche un est simplement ignorée.
     */
    public function import(Request $request, int $classeId): JsonResponse
    {
        $classe = $this->classe($classeId);

        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv']]);

        $import = new EmploiDuTempsImport($classe, $this->service);
        Excel::import($import, $request->file('file'));

        return ApiResponse::success([
            'imported' => $import->importedCount,
            'ignored' => $import->ignoredCount,
            'failed' => count($import->erreurs),
            'errors' => $import->erreurs,
            'matieres_introuvables' => $import->matieresIntrouvables,
            'enseignants_introuvables' => $import->enseignantsIntrouvables,
            'classes_introuvables' => $import->classesIntrouvables,
        ], $import->importedCount.' créneau(x) importé(s).');
    }

    /** Export au format relu par import() : même fichier pour sauvegarder, corriger en masse et réimporter. */
    public function export(int $classeId): BinaryFileResponse
    {
        $classe = $this->classe($classeId);

        return Excel::download(
            new EmploiDuTempsExport($classe),
            'emploi-du-temps-'.Str::slug($classe->nom).'.xlsx',
        );
    }

    private function valider(Request $request, Classe $classe): array
    {
        $data = $request->validate([
            'classe_matiere_id' => ['required', 'integer'],
            'jour' => ['required', 'integer', 'min:1', 'max:7'],
            'heure_debut' => ['required', 'date_format:H:i'],
            'heure_fin' => ['required', 'date_format:H:i', 'after:heure_debut'],
            'salle' => ['nullable', 'string', 'max:50'],
            // Tronc commun : les classes qui rejoignent celle-ci sur ce créneau.
            'classes_associees' => ['sometimes', 'array'],
            'classes_associees.*' => ['integer', 'distinct'],
        ]);

        $associees = collect($data['classes_associees'] ?? [])
            ->map(fn ($id) => (int) $id)
            // La classe porteuse n'a pas à figurer parmi celles qui la
            // rejoignent : elle y serait comptée deux fois à l'appel.
            ->reject(fn (int $id) => $id === $classe->id)
            ->unique()
            ->values();

        if ($associees->isNotEmpty()) {
            $valides = Classe::where('school_id', $classe->school_id)
                ->whereIn('id', $associees)
                ->pluck('id');

            abort_unless(
                $valides->count() === $associees->count(),
                422,
                "Une classe associée n'appartient pas à cet établissement.",
            );
        }

        abort_unless(
            ClasseMatiere::where('classe_id', $classe->id)->whereKey($data['classe_matiere_id'])->exists(),
            422,
            "Cette matière n'est pas affectée à la classe."
        );

        $data['classes_associees'] = $associees->all();

        return $data;
    }

    private function classe(int $id): Classe
    {
        return Classe::forSchool(Tenant::schoolIds())->findOrFail($id);
    }
}
