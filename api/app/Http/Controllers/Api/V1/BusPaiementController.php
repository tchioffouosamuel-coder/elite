<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\BusAffectation;
use App\Models\BusVersement;
use App\Services\BusPaiementService;
use App\Services\Sms\SmsService;
use App\Support\Pdf\RecuVersementBusGenerator;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Encaissement mensuel du transport scolaire — registre distinct de celui de
 * la scolarité (cf. `ScolariteController`), avec ses propres reçus.
 */
class BusPaiementController extends Controller
{
    public function __construct(
        private readonly BusPaiementService $service,
        private readonly SmsService $sms,
    ) {}

    /** Situation mensuelle de la souscription : dû, réglé et statut mois par mois, plus l'historique des versements. */
    public function situation(int $affectationId): JsonResponse
    {
        $affectation = $this->affectation($affectationId);
        $affectation->load(['eleve.classe', 'trajet', 'versements' => fn ($q) => $q->orderByDesc('mois')]);

        return ApiResponse::success([
            'affectation' => [
                'id' => $affectation->id,
                'tarif_mensuel' => $affectation->tarif_mensuel,
                'eleve' => [
                    'id' => $affectation->eleve->id,
                    'nom_complet' => $affectation->eleve->nom_complet,
                    'matricule' => $affectation->eleve->matricule,
                    'classe' => $affectation->eleve->classe?->nom,
                ],
                'trajet' => $affectation->trajet->nom,
            ],
            'situation_mensuelle' => $affectation->situation_mensuelle,
            'total_du' => $affectation->total_du,
            'total_paye' => $affectation->total_paye,
            'reste_a_payer' => $affectation->reste_a_payer,
            'statut_paiement' => $affectation->statut_paiement,
            'versements' => $affectation->versements->map(fn (BusVersement $v) => $this->resumerVersement($v))->values(),
        ]);
    }

    public function encaisser(Request $request, int $affectationId): JsonResponse
    {
        $affectation = $this->affectation($affectationId);

        $donnees = $request->validate([
            'mois' => ['required', 'date_format:Y-m-d'],
            'montant' => ['required', 'integer', 'min:1'],
            'date_versement' => ['nullable', 'date'],
            'mode' => ['nullable', 'in:especes,mobile_money,virement,cheque,depot_bancaire'],
            'reference_externe' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $versement = $this->service->encaisser($affectation, $donnees, $request->user()?->id);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        $this->confirmerParSms($affectation->fresh(['eleve.tuteurs']), $versement);

        return ApiResponse::created(
            ['versement_id' => $versement->id, 'numero_recu' => $versement->numero_recu],
            "Encaissement enregistré — reçu {$versement->numero_recu}.",
        );
    }

    public function annuler(Request $request, int $versementId): JsonResponse
    {
        $versement = $this->versement($versementId);

        $donnees = $request->validate(['motif' => ['required', 'string', 'min:3', 'max:255']]);

        try {
            $this->service->annuler($versement, $donnees['motif'], $request->user()?->id);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(null, "Reçu {$versement->numero_recu} annulé.");
    }

    public function recu(int $versementId): Response
    {
        $versement = $this->versement($versementId);

        return response((new RecuVersementBusGenerator)->build($versement), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="recu-bus-'.Str::slug($versement->numero_recu).'.pdf"',
        ]);
    }

    /**
     * Confirmation SMS au tuteur principal — un échec d'envoi ne remet jamais
     * en cause l'encaissement, déjà enregistré.
     */
    private function confirmerParSms(BusAffectation $affectation, BusVersement $versement): void
    {
        $tuteur = $affectation->eleve->tuteurs->firstWhere('pivot.is_principal', true)
            ?? $affectation->eleve->tuteurs->first();

        if (! $tuteur?->telephone) {
            return;
        }

        $mois = $versement->mois->translatedFormat('F Y');
        $message = "Paiement du transport scolaire de {$affectation->eleve->nom_complet} pour {$mois} : "
            ."{$this->francs($versement->montant)} reçu (reçu {$versement->numero_recu}).";

        $this->sms->envoyer($tuteur->telephone, $message);
    }

    private function francs(int $montant): string
    {
        return number_format($montant, 0, ',', ' ').' F';
    }

    /** @return array<string, mixed> */
    private function resumerVersement(BusVersement $v): array
    {
        return [
            'id' => $v->id,
            'numero_recu' => $v->numero_recu,
            'mois' => $v->mois->format('Y-m-d'),
            'date_versement' => $v->date_versement->format('Y-m-d'),
            'montant' => $v->montant,
            'mode' => $v->mode,
            'annule' => $v->estAnnule(),
        ];
    }

    private function affectation(int $id): BusAffectation
    {
        return BusAffectation::whereHas('trajet', fn ($q) => $q->forSchool(Tenant::schoolIds()))
            ->with('anneeScolaire')
            ->findOrFail($id);
    }

    private function versement(int $id): BusVersement
    {
        return BusVersement::forSchool(Tenant::schoolIds())->with('affectation.eleve')->findOrFail($id);
    }
}
