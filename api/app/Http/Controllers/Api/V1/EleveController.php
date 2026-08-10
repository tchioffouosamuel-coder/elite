<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\EleveExport;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreEleveRequest;
use App\Http\Requests\Api\V1\UpdateEleveRequest;
use App\Http\Resources\Api\V1\EleveResource;
use App\Models\Classe;
use App\Services\EleveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EleveController extends Controller
{
    public function __construct(private readonly EleveService $service) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->list(
            app('tenant.school_id'),
            $request->only(['search', 'classe_id', 'sexe', 'statut']),
            (int) $request->integer('per_page', 20),
        );

        return ApiResponse::paginated($paginator, EleveResource::class);
    }

    public function store(StoreEleveRequest $request): JsonResponse
    {
        $eleve = $this->service->create(app('tenant.school_id'), $request->validated());

        return ApiResponse::created(new EleveResource($eleve), 'Élève inscrit.');
    }

    public function show(int $id): JsonResponse
    {
        $eleve = $this->service->find(app('tenant.school_id'), $id);

        return ApiResponse::success(new EleveResource($eleve));
    }

    public function update(UpdateEleveRequest $request, int $id): JsonResponse
    {
        $eleve = $this->service->find(app('tenant.school_id'), $id);
        $eleve = $this->service->update($eleve, $request->validated());

        return ApiResponse::success(new EleveResource($eleve), 'Élève mis à jour.');
    }

    public function repartition(): JsonResponse
    {
        return ApiResponse::success($this->service->repartition(app('tenant.school_id')));
    }

    public function export(Request $request): BinaryFileResponse
    {
        $classeId = $request->integer('classe_id') ?: null;

        return Excel::download(new EleveExport(app('tenant.school_id'), $classeId), 'eleves.xlsx');
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv']]);

        $result = $this->service->importFromExcel(app('tenant.school_id'), $request->file('file'));

        return ApiResponse::success($result, "{$result['imported']} ligne(s) importée(s).");
    }

    public function photo(Request $request, int $id): JsonResponse
    {
        $request->validate(['photo' => ['required', 'file', 'mimes:jpeg,jpg,png', 'max:5120']]);

        $eleve = $this->service->find(app('tenant.school_id'), $id);
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

        $eleve = $this->service->find(app('tenant.school_id'), $id);
        $eleve = $this->service->transferer($eleve, $classe);

        return ApiResponse::success(
            new EleveResource($eleve),
            'Élève transféré vers '.$classe->school->name.' — '.$classe->nom.'.'
        );
    }
}
