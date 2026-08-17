<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DossierScolariteResource;
use App\Models\AnneeScolaire;
use App\Models\DossierScolarite;
use App\Models\Eleve;
use App\Models\Versement;
use App\Services\ScolariteService;
use App\Services\Sms\SmsService;
use App\Support\Pdf\RecuVersementGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class ScolariteController extends Controller
{
    public function __construct(
        private readonly ScolariteService $service,
        private readonly SmsService $sms,
    ) {}

    /** Situation de recouvrement, et liste des insolvables via `?statut=impaye|partiel`. */
    public function situation(Request $request): JsonResponse
    {
        $situation = $this->service->situation(
            app('tenant.school_id'),
            $this->annee($request)->id,
            [
                'classe_id' => $request->integer('classe_id') ?: null,
                'statut' => $request->string('statut')->toString() ?: null,
            ],
        );

        return ApiResponse::success([
            'dossiers' => DossierScolariteResource::collection($situation['dossiers']),
            'totaux' => $situation['totaux'],
        ]);
    }

    /** Dossier de l'élève, ouvert au besoin depuis la grille tarifaire. */
    public function dossier(Request $request, int $eleveId): JsonResponse
    {
        $eleve = Eleve::forSchool(app('tenant.school_id'))->with('classe')->findOrFail($eleveId);

        $dossier = $this->service->dossier($eleve, $this->annee($request));
        $dossier->load('eleve.classe');
        $dossier->loadMissing(['fraisAnnexes', 'versements' => fn ($q) => $q->valides()->with('lignes'), 'busAffectations.trajet']);

        return ApiResponse::success(new DossierScolariteResource($dossier, avecRubriques: true));
    }

    public function encaisser(Request $request, int $dossierId): JsonResponse
    {
        $dossier = $this->dossierDuTenant($dossierId);

        $donnees = $request->validate([
            'montant' => ['required', 'integer', 'min:1'],
            'date_versement' => ['nullable', 'date'],
            'mode' => ['nullable', 'in:especes,mobile_money,virement,cheque,depot_bancaire'],
            'reference_externe' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
            'lignes' => ['nullable', 'array', 'min:1'],
            'lignes.*.affectation' => ['required_with:lignes', 'in:scolarite,frais_annexe,report_dette,bus'],
            'lignes.*.dossier_frais_annexe_id' => ['nullable', 'integer'],
            'lignes.*.libelle' => ['nullable', 'string', 'max:150'],
            'lignes.*.montant' => ['required_with:lignes', 'integer', 'min:1'],
        ]);

        try {
            $versement = $this->service->encaisser($dossier, $donnees, $request->user()?->id);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        $this->confirmerParSms($dossier->fresh(['eleve.tuteurs']), $versement);

        return ApiResponse::created(
            ['versement_id' => $versement->id, 'numero_recu' => $versement->numero_recu],
            "Encaissement enregistré — reçu {$versement->numero_recu}.",
        );
    }

    /**
     * Confirmation SMS au tuteur principal de l'élève — un échec d'envoi ne
     * remet jamais en cause l'encaissement, déjà enregistré.
     */
    private function confirmerParSms(DossierScolarite $dossier, Versement $versement): void
    {
        $tuteur = $dossier->eleve->tuteurs->firstWhere('pivot.is_principal', true)
            ?? $dossier->eleve->tuteurs->first();

        if (! $tuteur?->telephone) {
            return;
        }

        $reste = $dossier->fresh()?->reste_a_payer ?? 0;
        $message = "Paiement de {$this->francs($versement->montant)} reçu pour {$dossier->eleve->nom_complet} (reçu {$versement->numero_recu}). "
            .($reste > 0 ? "Reste à payer : {$this->francs($reste)}." : 'Scolarité soldée.');

        $this->sms->envoyer($tuteur->telephone, $message);
    }

    private function francs(int $montant): string
    {
        return number_format($montant, 0, ',', ' ').' F';
    }

    public function annuler(Request $request, int $versementId): JsonResponse
    {
        $versement = Versement::forSchool(app('tenant.school_id'))->findOrFail($versementId);

        $donnees = $request->validate(['motif' => ['required', 'string', 'min:3', 'max:255']]);

        try {
            $this->service->annuler($versement, $donnees['motif'], $request->user()?->id);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(null, "Reçu {$versement->numero_recu} annulé.");
    }

    /** Reçu au format du ticket de caisse (rouleau 80 mm). */
    public function recu(int $versementId): Response
    {
        $versement = Versement::forSchool(app('tenant.school_id'))->findOrFail($versementId);

        return response((new RecuVersementGenerator)->build($versement), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="recu-'.Str::slug($versement->numero_recu).'.pdf"',
        ]);
    }

    private function dossierDuTenant(int $id): DossierScolarite
    {
        return DossierScolarite::forSchool(app('tenant.school_id'))->avecTotaux()->findOrFail($id);
    }

    /**
     * Année visée : celle passée en paramètre, sinon l'année active. Une
     * situation financière n'a aucun sens hors d'une année précise.
     */
    private function annee(Request $request): AnneeScolaire
    {
        $schoolId = app('tenant.school_id');

        if ($id = $request->integer('annee_scolaire_id')) {
            return AnneeScolaire::where('school_id', $schoolId)->findOrFail($id);
        }

        return AnneeScolaire::where('school_id', $schoolId)->where('is_active', true)->firstOrFail();
    }
}
