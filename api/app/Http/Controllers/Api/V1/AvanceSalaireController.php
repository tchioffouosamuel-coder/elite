<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AvanceSalaire;
use App\Models\Personnel;
use App\Services\AvanceSalaireService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class AvanceSalaireController extends Controller
{
    public function __construct(private readonly AvanceSalaireService $service) {}

    public function index(Request $request): JsonResponse
    {
        $filtres = [
            'personnel_id' => $request->integer('personnel_id') ?: null,
            'statut' => $request->string('statut')->toString() ?: null,
        ];

        $avances = $this->service->lister(Tenant::schoolIds(), $filtres);

        return ApiResponse::success([
            'avances' => $avances->map(fn (AvanceSalaire $a) => $this->resumer($a))->values(),
            'totaux' => $this->service->totaux(Tenant::schoolIds()),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'personnel_id' => ['required', 'integer', Rule::exists('personnels', 'id')->where('school_id', Tenant::schoolIds())],
            'montant' => ['required', 'integer', 'min:1'],
            'date_avance' => ['required', 'date'],
            'motif' => ['nullable', 'string', 'max:255'],
        ]);

        // L'avance appartient à l'école de l'agent choisi, pas à un contexte
        // ambiant : en mode agrégé, deviner serait arbitraire.
        $schoolId = Personnel::findOrFail($donnees['personnel_id'])->school_id;

        $avance = $this->service->accorder($schoolId, $donnees, $request->user()?->id);

        return ApiResponse::created($this->resumer($avance->load('personnel.school')), 'Avance accordée.');
    }

    public function rembourser(Request $request, int $id): JsonResponse
    {
        $avance = $this->service->trouver(Tenant::schoolIds(), $id);

        $donnees = $request->validate([
            'montant' => ['required', 'integer', 'min:1'],
            'date_remboursement' => ['nullable', 'date'],
            'mode' => ['nullable', 'in:retenue_salaire,versement_direct'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->executer(fn () => ApiResponse::success(
            $this->resumer($this->service->rembourser($avance, $donnees)),
            'Remboursement enregistré.',
        ));
    }

    public function annuler(Request $request, int $id): JsonResponse
    {
        $avance = $this->service->trouver(Tenant::schoolIds(), $id);

        $donnees = $request->validate(['motif' => ['required', 'string', 'min:3', 'max:255']]);

        return $this->executer(fn () => ApiResponse::success(
            $this->resumer($this->service->annuler($avance, $donnees['motif'], $request->user()?->id)),
            'Avance annulée.',
        ));
    }

    /** @return array<string, mixed> */
    private function resumer(AvanceSalaire $avance): array
    {
        $avance->loadMissing('personnel.school', 'remboursements');

        return [
            'id' => $avance->id,
            'personnel' => [
                'id' => $avance->personnel?->id,
                'nom_complet' => $avance->personnel?->nom_complet,
                'matricule' => $avance->personnel?->matricule,
                'fonction' => $avance->personnel?->fonction,
            ],
            'school' => $avance->personnel?->school ? [
                'id' => $avance->personnel->school->id,
                'name' => $avance->personnel->school->name,
                'code' => $avance->personnel->school->code,
                'type' => $avance->personnel->school->type,
            ] : null,
            'montant' => $avance->montant,
            'date_avance' => $avance->date_avance->format('Y-m-d'),
            'motif' => $avance->motif,
            'montant_rembourse' => $avance->montant_rembourse,
            'solde' => $avance->solde,
            'statut' => $avance->statut,
            'annule' => $avance->estAnnulee(),
            'motif_annulation' => $avance->motif_annulation,
            'remboursements' => $avance->remboursements->map(fn ($r) => [
                'id' => $r->id,
                'montant' => $r->montant,
                'date_remboursement' => $r->date_remboursement->format('Y-m-d'),
                'mode' => $r->mode,
                'note' => $r->note,
            ])->values(),
        ];
    }

    /** Les refus métier du service sont des 422, pas des erreurs serveur. */
    private function executer(callable $action): JsonResponse
    {
        try {
            return $action();
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }
}
