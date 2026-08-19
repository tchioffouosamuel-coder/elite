<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\EleveExport;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreEleveRequest;
use App\Http\Requests\Api\V1\UpdateEleveRequest;
use App\Http\Resources\Api\V1\EleveResource;
use App\Models\ActivityLog;
use App\Models\Classe;
use App\Services\EleveService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EleveController extends Controller
{
    public function __construct(private readonly EleveService $service) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->list(
            Tenant::schoolIds(),
            $request->only(['search', 'classe_id', 'sexe', 'statut']),
            (int) $request->integer('per_page', 20),
        );

        return ApiResponse::paginated($paginator, EleveResource::class);
    }

    /**
     * L'école d'un élève découle de sa classe, pas d'un champ dédié : en
     * l'absence de classe (élève non encore affecté), on retombe sur
     * Tenant::resolveWriteSchoolId() qui exige alors un school_id explicite
     * en mode agrégé plutôt que de deviner.
     */
    public function store(StoreEleveRequest $request): JsonResponse
    {
        $data = $request->validated();

        $schoolId = isset($data['classe_id'])
            ? Classe::forSchool(Tenant::schoolIds())->findOrFail($data['classe_id'])->school_id
            : Tenant::resolveWriteSchoolId($request->integer('school_id') ?: null);

        $eleve = $this->service->create($schoolId, $data);

        ActivityLog::enregistrer($request->user(), 'eleve.cree', "Inscription de {$eleve->nom_complet}.", $eleve);

        return ApiResponse::created(new EleveResource($eleve), 'Élève inscrit.');
    }

    public function show(int $id): JsonResponse
    {
        $eleve = $this->service->find(Tenant::schoolIds(), $id);

        return ApiResponse::success(new EleveResource($eleve));
    }

    public function update(UpdateEleveRequest $request, int $id): JsonResponse
    {
        $eleve = $this->service->find(Tenant::schoolIds(), $id);
        $eleve = $this->service->update($eleve, $request->validated());

        return ApiResponse::success(new EleveResource($eleve), 'Élève mis à jour.');
    }

    public function repartition(): JsonResponse
    {
        return ApiResponse::success($this->service->repartition(Tenant::schoolIds()));
    }

    public function export(Request $request): BinaryFileResponse
    {
        $classeId = $request->integer('classe_id') ?: null;

        return Excel::download(new EleveExport(Tenant::schoolIds(), $classeId), 'eleves.xlsx');
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv']]);

        $result = $this->service->importFromExcel(app('tenant.school_id'), $request->file('file'));

        return ApiResponse::success($result, "{$result['imported']} élève(s) créé(s), {$result['updated']} mis à jour.");
    }

    public function photo(Request $request, int $id): JsonResponse
    {
        $request->validate(['photo' => ['required', 'file', 'mimes:jpeg,jpg,png', 'max:5120']]);

        $eleve = $this->service->find(Tenant::schoolIds(), $id);
        $eleve = $this->service->updatePhoto($eleve, $request->file('photo'));

        return ApiResponse::success(new EleveResource($eleve), 'Photo mise à jour.');
    }

    /**
     * Transfert d'un élève vers une autre école du complexe. La classe d'arrivée
     * est obligatoire : sans elle l'élève se retrouverait dans un établissement
     * où il n'est rattaché à aucun enseignement, invisible des listes de classe.
     */
    public function transfert(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole('super_admin')) {
            return ApiResponse::forbidden('Seul le super administrateur peut transférer un élève entre établissements.');
        }

        $data = $request->validate([
            'school_id' => ['required', 'integer'],
            'classe_id' => ['required', 'integer'],
        ]);

        if (! $user->ecolesAccessibles()->contains('id', $data['school_id'])) {
            return ApiResponse::forbidden("Cet établissement n'est pas accessible à votre compte.");
        }

        $classe = Classe::where('school_id', $data['school_id'])->find($data['classe_id']);

        if (! $classe) {
            return ApiResponse::error("La classe d'arrivée n'appartient pas à l'établissement de destination.", 422);
        }

        $eleve = $this->service->find(Tenant::schoolIds(), $id);
        $eleve = $this->service->transferer($eleve, $classe);

        return ApiResponse::success(
            new EleveResource($eleve),
            'Élève transféré vers ' . $classe->school->name . ' — ' . $classe->nom . '.'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $eleve = $this->service->find(Tenant::schoolIds(), $id);
        $this->service->delete($eleve);

        return ApiResponse::success(null, 'Élève supprimé.');
    }

    public function batchDelete(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $schoolId = Tenant::schoolIds();
        $deleted = 0;

        foreach ($data['ids'] as $id) {
            $eleve = $this->service->find($schoolId, $id);
            $this->service->delete($eleve);
            $deleted++;
        }

        return ApiResponse::success(['deleted' => $deleted], "{$deleted} élève(s) supprimé(s).");
    }

    /** Bascule un lot d'élèves vers une autre classe de la même école. */
    public function batchTransfertClasse(Request $request): JsonResponse
    {
        $schoolId = Tenant::schoolIds();

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'classe_id' => ['required', 'integer', Rule::exists('classes', 'id')->where('school_id', $schoolId)],
        ]);

        $transferes = 0;

        foreach ($data['ids'] as $id) {
            $eleve = $this->service->find($schoolId, $id);
            $this->service->update($eleve, ['classe_id' => $data['classe_id']]);
            $transferes++;
        }

        return ApiResponse::success(['transferes' => $transferes], "{$transferes} élève(s) transféré(s).");
    }

    /**
     * Bascule un lot d'élèves vers une autre école du complexe — même
     * restriction que le transfert unitaire : seul le super administrateur y
     * est autorisé, et la classe d'arrivée doit appartenir à l'école visée.
     */
    public function batchTransfertEcole(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole('super_admin')) {
            return ApiResponse::forbidden('Seul le super administrateur peut transférer des élèves entre établissements.');
        }

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'school_id' => ['required', 'integer'],
            'classe_id' => ['required', 'integer'],
        ]);

        if (! $user->ecolesAccessibles()->contains('id', $data['school_id'])) {
            return ApiResponse::forbidden("Cet établissement n'est pas accessible à votre compte.");
        }

        $classe = Classe::where('school_id', $data['school_id'])->find($data['classe_id']);

        if (! $classe) {
            return ApiResponse::error("La classe d'arrivée n'appartient pas à l'établissement de destination.", 422);
        }

        $schoolId = Tenant::schoolIds();
        $transferes = 0;

        foreach ($data['ids'] as $id) {
            $eleve = $this->service->find($schoolId, $id);
            $this->service->transferer($eleve, $classe);
            $transferes++;
        }

        return ApiResponse::success(['transferes' => $transferes], "{$transferes} élève(s) transféré(s) vers {$classe->school->name} — {$classe->nom}.");
    }
}
