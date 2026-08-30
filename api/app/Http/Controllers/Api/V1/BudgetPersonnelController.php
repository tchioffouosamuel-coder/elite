<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Concerns\ScopedRules;
use App\Models\BudgetPersonnel;
use App\Models\Depense;
use App\Models\School;
use App\Services\BudgetPersonnelService;
use App\Support\Pdf\BudgetPersonnelBilanGenerator;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class BudgetPersonnelController extends Controller
{
    use ScopedRules;

    public function __construct(private readonly BudgetPersonnelService $service) {}

    public function index(Request $request): JsonResponse
    {
        $personnelId = $request->integer('personnel_id') ?: null;

        return ApiResponse::success([
            'budgets' => $this->service->lister(Tenant::schoolIds(), $personnelId)
                ->map(fn(BudgetPersonnel $b) => $this->resumer($b))->values(),
            'totaux' => $this->service->totaux(Tenant::schoolIds()),
        ]);
    }

    /**
     * Budgets actifs, pour le sélecteur « Source = Budget alloué » du
     * formulaire de dépense — accessible à quiconque saisit une dépense, pas
     * seulement à qui peut allouer un budget.
     */
    public function actifs(): JsonResponse
    {
        return ApiResponse::success(
            $this->service->listerActifs(Tenant::schoolIds())->map(fn(BudgetPersonnel $b) => $this->resumer($b))->values()
        );
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::success($this->resumerDetail($this->budget($id)));
    }

    public function store(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'personnel_id' => ['required', 'integer', $this->scopedExists('personnels')],
            'libelle' => ['required', 'string', 'max:200'],
            'montant_alloue' => ['required', 'integer', 'min:1'],
            'date_allocation' => ['nullable', 'date'],
            'annee_scolaire_id' => ['nullable', 'integer'],
            'note_gestion' => ['nullable', 'string', 'max:2000'],
        ]);

        $schoolId = Tenant::resolveWriteSchoolId($request->integer('school_id') ?: null);
        $budget = $this->service->allouer($schoolId, $donnees, $request->user()?->id);

        return ApiResponse::created($this->resumer($budget), 'Budget alloué.');
    }

    /** Le personnel concerné précise ici comment il compte gérer son enveloppe. */
    public function modifierNoteGestion(Request $request, int $id): JsonResponse
    {
        $donnees = $request->validate(['note_gestion' => ['required', 'string', 'max:2000']]);

        $budget = $this->service->modifierNoteGestion($this->budget($id), $donnees['note_gestion']);

        return ApiResponse::success($this->resumer($budget), 'Note de gestion mise à jour.');
    }

    public function annuler(Request $request, int $id): JsonResponse
    {
        $donnees = $request->validate(['motif' => ['required', 'string', 'min:3', 'max:255']]);

        try {
            $budget = $this->service->annuler($this->budget($id), $donnees['motif'], $request->user()?->id);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($this->resumer($budget), 'Budget clôturé.');
    }

    /** Bilan de gestion du budget en PDF : alloué, dépenses imputées, solde et note de gestion. */
    public function bilanPdf(Request $request, int $id): Response
    {
        $budget = $this->budget($id);
        $du = $request->string('du')->toString() ?: null;
        $au = $request->string('au')->toString() ?: null;

        $bilan = $this->service->bilan($budget, $du, $au);
        $pdf = (new BudgetPersonnelBilanGenerator)->build($budget, $bilan, $du, $au);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="bilan-budget-' . $budget->id . '.pdf"',
        ]);
    }

    /** @return array<string, mixed> */
    private function resumer(BudgetPersonnel $budget): array
    {
        return [
            'id' => $budget->id,
            'libelle' => $budget->libelle,
            'montant_alloue' => $budget->montant_alloue,
            'montant_depense' => $budget->montant_depense,
            'solde' => $budget->solde,
            'statut' => $budget->statut,
            'date_allocation' => $budget->date_allocation?->format('Y-m-d'),
            'note_gestion' => $budget->note_gestion,
            'motif_annulation' => $budget->motif_annulation,
            'personnel' => $budget->personnel ? [
                'id' => $budget->personnel->id,
                'nom_complet' => $budget->personnel->nom_complet,
                'matricule' => $budget->personnel->matricule,
            ] : null,
            'school' => $budget->school ? [
                'id' => $budget->school->id,
                'name' => $budget->school->name,
                'code' => $budget->school->code,
                'type' => $budget->school->type,
            ] : null,
        ];
    }

    /** @return array<string, mixed> */
    private function resumerDetail(BudgetPersonnel $budget): array
    {
        return [
            ...$this->resumer($budget),
            'depenses' => $budget->depenses->map(fn(Depense $d) => [
                'id' => $d->id,
                'date_depense' => $d->date_depense?->format('Y-m-d'),
                'libelle' => $d->libelle,
                'montant' => $d->montant,
                'statut' => $d->statut,
                'compte' => $d->compte?->libelle,
            ])->values(),
        ];
    }

    private function budget(int $id): BudgetPersonnel
    {
        return $this->service->trouver(Tenant::schoolIds(), $id);
    }
}
