<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\PersonnelExport;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreateLoginAccountRequest;
use App\Http\Requests\Api\V1\StorePersonnelRequest;
use App\Http\Requests\Api\V1\UpdatePersonnelRequest;
use App\Http\Resources\Api\V1\PersonnelResource;
use App\Models\ActivityLog;
use App\Models\AnneeScolaire;
use App\Models\School;
use App\Services\AttestationEmployeurService;
use App\Services\CompteAgentService;
use App\Services\PersonnelService;
use App\Support\Pdf\IdentifiantsGenerator;
use App\Support\Pdf\PersonnelFichierGenerator;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class PersonnelController extends Controller
{
    public function __construct(
        private readonly PersonnelService $service,
        private readonly CompteAgentService $comptes,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->list(
            Tenant::schoolIds(),
            $request->only(['search', 'departement_id', 'fonction_id', 'fonction_label', 'statut', 'attribution']),
            (int) $request->integer('per_page', 20),
        );

        return ApiResponse::paginated($paginator, PersonnelResource::class);
    }

    public function store(StorePersonnelRequest $request): JsonResponse
    {
        $data = $request->validated();
        $schoolId = Tenant::resolveWriteSchoolId($data['school_id'] ?? null);
        unset($data['school_id']);

        $personnel = $this->service->create($schoolId, $data);

        ActivityLog::enregistrer($request->user(), 'personnel.cree', "Ajout de {$personnel->nom_complet}.", $personnel);

        return ApiResponse::created(new PersonnelResource($personnel), 'Membre du personnel créé.');
    }

    public function show(int $id): JsonResponse
    {
        $personnel = $this->service->find(Tenant::schoolIds(), $id);

        return ApiResponse::success(new PersonnelResource($personnel));
    }

    public function update(UpdatePersonnelRequest $request, int $id): JsonResponse
    {
        $personnel = $this->service->find(Tenant::schoolIds(), $id);
        $personnel = $this->service->update($personnel, $request->validated());

        return ApiResponse::success(new PersonnelResource($personnel), 'Membre du personnel mis à jour.');
    }

    public function archive(int $id): JsonResponse
    {
        $personnel = $this->service->find(Tenant::schoolIds(), $id);
        $personnel = $this->service->archive($personnel);

        return ApiResponse::success(new PersonnelResource($personnel), 'Membre du personnel archivé.');
    }

    public function reactivate(int $id): JsonResponse
    {
        $personnel = $this->service->find(Tenant::schoolIds(), $id);
        $personnel = $this->service->reactivate($personnel);

        return ApiResponse::success(new PersonnelResource($personnel), 'Membre du personnel réactivé.');
    }

    public function createAccount(CreateLoginAccountRequest $request, int $id): JsonResponse
    {
        $personnel = $this->service->find(Tenant::schoolIds(), $id);
        $user = $this->service->createLoginAccount(
            $personnel,
            $request->string('email')->toString() ?: null,
            $request->string('password')->toString() ?: null,
        );

        return ApiResponse::created(
            ['user_id' => $user->id, 'email' => $user->email],
            "Accès ouvert : {$user->email}.",
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $personnel = $this->service->find(app('tenant.school_id'), $id);
        $this->service->delete($personnel);

        return ApiResponse::success(null, 'Membre du personnel supprimé.');
    }

    public function batchDelete(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $schoolId = app('tenant.school_id');
        $deleted = 0;

        foreach ($data['ids'] as $id) {
            $personnel = $this->service->find($schoolId, $id);
            $this->service->delete($personnel);
            $deleted++;
        }

        return ApiResponse::success(['deleted' => $deleted], "{$deleted} membre(s) du personnel supprimé(s).");
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv']]);

        $result = $this->service->importFromExcel(app('tenant.school_id'), $request->file('file'));

        return ApiResponse::success($result, "{$result['imported']} ligne(s) importée(s).");
    }

    public function export(): BinaryFileResponse
    {
        return Excel::download(new PersonnelExport(Tenant::schoolIds()), 'personnel.xlsx');
    }

    /**
     * Fichier du personnel : registre nominatif des agents en poste, à signer
     * et archiver. L'export Excel voisin sert au retraitement des données ;
     * celui-ci est le document officiel.
     */
    public function fichier(): Response
    {
        $schoolIds = Tenant::schoolIds();
        $schools = School::whereIn('id', $schoolIds)->orderBy('name')->get();
        $donnees = $this->service->fichier($schoolIds);

        if (Tenant::isAggregate()) {
            $documents = $schools->map(fn(School $school) => [
                'donnees' => $this->service->fichier($school->id),
                'school' => $school,
                'annee' => AnneeScolaire::where('school_id', $school->id)->where('is_active', true)->first(),
            ])->all();
            $pdf = (new PersonnelFichierGenerator)->buildMany($documents);
            $nom = 'fichier-personnel-toutes-les-ecoles';
        } else {
            $school = $schools->firstOrFail();
            $annee = AnneeScolaire::where('school_id', $school->id)->where('is_active', true)->first();
            $pdf = (new PersonnelFichierGenerator)->build($donnees, $school, $annee);
            $nom = 'fichier-personnel-' . Str::slug($school->name);
        }

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $nom . '.pdf"',
        ]);
    }

    /**
     * Identifiants de connexion de tous les comptes actifs de l'établissement
     * — le mot de passe n'y figure que pour les comptes qui portent encore
     * celui distribué à l'ouverture de l'accès (cf. CompteAgentService::identifiants).
     * Document confidentiel : à remettre en main propre plutôt qu'à archiver.
     */
    public function identifiants(): Response
    {
        $schoolIds = Tenant::schoolIds();
        $schools = School::whereIn('id', $schoolIds)->orderBy('name')->get();

        if (Tenant::isAggregate()) {
            $documents = $schools->map(fn(School $school) => [
                'donnees' => $this->comptes->identifiants($school->id),
                'school' => $school,
            ])->all();
            $pdf = (new IdentifiantsGenerator)->buildMany($documents);
            $nom = 'identifiants-toutes-les-ecoles';
        } else {
            $school = $schools->firstOrFail();
            $pdf = (new IdentifiantsGenerator)->build($this->comptes->identifiants($school->id), $school);
            $nom = 'identifiants-' . Str::slug($school->name);
        }

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $nom . '.pdf"',
        ]);
    }

    /**
     * Attestation de l'employeur, avec periode d'absence facultative.
     */
    public function attestationEmployeur(Request $request, int $id): BinaryFileResponse
    {
        $personnel = $this->service->find(app('tenant.school_id'), $id);

        $conge = $request->validate([
            'debut' => ['nullable', 'date'],
            'fin' => ['nullable', 'date', 'after_or_equal:debut'],
            'prolongation' => ['nullable', 'date', 'after_or_equal:fin'],
            'motif' => ['nullable', 'string', 'max:120'],
        ]);

        $path = app(AttestationEmployeurService::class)->generer($personnel, $conge, $request->user()?->id);

        return response()
            ->download($path, 'attestation-employeur-' . Str::slug($personnel->nom_complet) . '.docx')
            ->deleteFileAfterSend();
    }
}
