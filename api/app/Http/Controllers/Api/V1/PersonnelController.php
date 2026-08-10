<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\PersonnelExport;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreateLoginAccountRequest;
use App\Http\Requests\Api\V1\StorePersonnelRequest;
use App\Http\Requests\Api\V1\UpdatePersonnelRequest;
use App\Http\Resources\Api\V1\PersonnelResource;
use App\Services\PersonnelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PersonnelController extends Controller
{
    public function __construct(private readonly PersonnelService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->list(
            app('tenant.school_id'),
            $request->only(['search', 'departement_id', 'statut']),
            (int) $request->integer('per_page', 20),
        );

        return ApiResponse::paginated($paginator, PersonnelResource::class);
    }

    public function store(StorePersonnelRequest $request): JsonResponse
    {
        $personnel = $this->service->create(app('tenant.school_id'), $request->validated());

        return ApiResponse::created(new PersonnelResource($personnel), 'Membre du personnel créé.');
    }

    public function show(int $id): JsonResponse
    {
        $personnel = $this->service->find(app('tenant.school_id'), $id);

        return ApiResponse::success(new PersonnelResource($personnel));
    }

    public function update(UpdatePersonnelRequest $request, int $id): JsonResponse
    {
        $personnel = $this->service->find(app('tenant.school_id'), $id);
        $personnel = $this->service->update($personnel, $request->validated());

        return ApiResponse::success(new PersonnelResource($personnel), 'Membre du personnel mis à jour.');
    }

    public function archive(int $id): JsonResponse
    {
        $personnel = $this->service->find(app('tenant.school_id'), $id);
        $personnel = $this->service->archive($personnel);

        return ApiResponse::success(new PersonnelResource($personnel), 'Membre du personnel archivé.');
    }

    public function reactivate(int $id): JsonResponse
    {
        $personnel = $this->service->find(app('tenant.school_id'), $id);
        $personnel = $this->service->reactivate($personnel);

        return ApiResponse::success(new PersonnelResource($personnel), 'Membre du personnel réactivé.');
    }

    public function createAccount(CreateLoginAccountRequest $request, int $id): JsonResponse
    {
        $personnel = $this->service->find(app('tenant.school_id'), $id);
        $user = $this->service->createLoginAccount(
            $personnel,
            $request->string('email')->toString(),
            $request->string('role')->toString(),
            $request->string('password')->toString() ?: null,
        );

        return ApiResponse::created(['user_id' => $user->id], 'Compte de connexion créé.');
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv']]);

        $result = $this->service->importFromExcel(app('tenant.school_id'), $request->file('file'));

        return ApiResponse::success($result, "{$result['imported']} ligne(s) importée(s).");
    }

    public function export(): BinaryFileResponse
    {
        return Excel::download(new PersonnelExport(app('tenant.school_id')), 'personnel.xlsx');
    }
}
