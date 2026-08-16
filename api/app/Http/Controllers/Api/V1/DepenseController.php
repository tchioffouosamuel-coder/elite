<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\CompteComptable;
use App\Models\Depense;
use App\Models\School;
use App\Services\DepenseService;
use App\Support\Pdf\BilanDepensesGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class DepenseController extends Controller
{
    public function __construct(private readonly DepenseService $service) {}

    /** Dépenses de la période, ventilation par compte et totaux. */
    public function index(Request $request): JsonResponse
    {
        $bilan = $this->service->bilan(app('tenant.school_id'), $this->filtres($request));

        return ApiResponse::success([
            'depenses' => $bilan['depenses']->map(fn(Depense $d) => $this->resumer($d))->values(),
            'par_compte' => $bilan['par_compte'],
            'totaux' => $bilan['totaux'],
        ]);
    }

    /** Plan de comptes, pour le choix du poste d'imputation. */
    public function comptes(): JsonResponse
    {
        $comptes = CompteComptable::where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'libelle', 'classe', 'sens']);

        return ApiResponse::success($comptes);
    }

    public function store(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'libelle' => ['required', 'string', 'max:200'],
            'montant' => ['required', 'integer', 'min:1'],
            'date_depense' => ['nullable', 'date'],
            'compte_comptable_id' => ['nullable', 'integer', 'exists:comptes_comptables,id'],
            'annee_scolaire_id' => ['nullable', 'integer'],
            'mode' => ['nullable', 'in:especes,mobile_money,virement,cheque,depot_bancaire'],
            'beneficiaire' => ['nullable', 'string', 'max:150'],
            'reference_facture' => ['nullable', 'string', 'max:100'],
            'responsable' => ['nullable', 'string', 'max:150'],
            'statut' => ['nullable', 'in:engagee,payee'],
            'justificatif' => ['nullable', 'file', 'mimes:jpeg,jpg,png,pdf', 'max:5120'],
        ]);

        $depense = $this->service->enregistrer(
            app('tenant.school_id'),
            $donnees,
            $request->user()?->id,
            $request->file('justificatif'),
        );

        return ApiResponse::created($this->resumer($depense), 'Dépense enregistrée.');
    }

    public function payer(Request $request, int $id): JsonResponse
    {
        $depense = $this->depense($id);

        $donnees = $request->validate([
            'mode' => ['nullable', 'in:especes,mobile_money,virement,cheque,depot_bancaire'],
            'date_depense' => ['nullable', 'date'],
        ]);

        try {
            $depense = $this->service->payer($depense, $donnees['mode'] ?? null, $donnees['date_depense'] ?? null);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($this->resumer($depense), 'Règlement enregistré.');
    }

    public function annuler(Request $request, int $id): JsonResponse
    {
        $depense = $this->depense($id);

        $donnees = $request->validate(['motif' => ['required', 'string', 'min:3', 'max:255']]);

        try {
            $depense = $this->service->annuler($depense, $donnees['motif'], $request->user()?->id);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($this->resumer($depense), 'Dépense annulée.');
    }

    /** @return array<string, mixed> */
    private function resumer(Depense $depense): array
    {
        $depense->loadMissing(['compte', 'saisisseur']);

        return [
            'id' => $depense->id,
            'date_depense' => $depense->date_depense?->format('Y-m-d'),
            'libelle' => $depense->libelle,
            'montant' => $depense->montant,
            'mode' => $depense->mode,
            'statut' => $depense->statut,
            'beneficiaire' => $depense->beneficiaire,
            'reference_facture' => $depense->reference_facture,
            'responsable' => $depense->responsable,
            'compte' => $depense->compte ? [
                'id' => $depense->compte->id,
                'code' => $depense->compte->code,
                'libelle' => $depense->compte->libelle,
            ] : null,
            'justificatif_url' => $depense->justificatif_path
                ? asset('storage/' . $depense->justificatif_path)
                : null,
            'saisi_par' => $depense->saisisseur?->name,
            'motif_annulation' => $depense->motif_annulation,
        ];
    }

    private function depense(int $id): Depense
    {
        return Depense::forSchool(app('tenant.school_id'))->findOrFail($id);
    }

    /** @return array<string, mixed> */
    private function filtres(Request $request): array
    {
        $request->validate([
            'du' => ['nullable', 'date'],
            'au' => ['nullable', 'date'],
            'statut' => ['nullable', 'in:engagee,payee,annulee'],
        ]);

        return [
            'du' => $request->string('du')->toString() ?: null,
            'au' => $request->string('au')->toString() ?: null,
            'compte_comptable_id' => $request->integer('compte_comptable_id') ?: null,
            'statut' => $request->string('statut')->toString() ?: null,
        ];
    }

    /** Bilan de la periode en PDF : ventilation par poste, puis detail chronologique. */
    public function bilanPdf(Request $request): Response
    {
        $schoolId = app('tenant.school_id');
        $du = $request->string('du')->toString() ?: null;
        $au = $request->string('au')->toString() ?: null;

        $bilan = $this->service->bilan($schoolId, ['du' => $du, 'au' => $au]);

        $pdf = (new BilanDepensesGenerator)->build(
            School::findOrFail($schoolId),
            $bilan['depenses'],
            $bilan['par_compte'],
            $bilan['totaux'],
            $du,
            $au,
        );

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="bilan-depenses.pdf"',
        ]);
    }
}
