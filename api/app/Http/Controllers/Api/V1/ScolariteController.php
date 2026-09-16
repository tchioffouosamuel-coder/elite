<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DossierScolariteResource;
use App\Models\AnneeScolaire;
use App\Models\DossierScolarite;
use App\Models\Eleve;
use App\Models\Versement;
use App\Services\Notifications\NotificationPaiementService;
use App\Services\ScolariteService;
use App\Support\Pdf\RecuVersementGenerator;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class ScolariteController extends Controller
{
    public function __construct(
        private readonly ScolariteService $service,
        private readonly NotificationPaiementService $notifications,
    ) {}

    /** Situation de recouvrement, et liste des insolvables via `?statut=impaye|partiel`. */
    public function situation(Request $request): JsonResponse
    {
        $filtres = [
            'classe_id' => $request->integer('classe_id') ?: null,
            'statut' => $request->string('statut')->toString() ?: null,
        ];
        $schoolIds = Tenant::schoolIds();
        $situation = count($schoolIds) > 1
            ? $this->service->situationAgregee($schoolIds, $filtres)
            : $this->service->situation($schoolIds[0], $this->annee($request, $schoolIds[0])->id, $filtres);

        return ApiResponse::success([
            'dossiers' => DossierScolariteResource::collection($situation['dossiers']),
            'totaux' => $situation['totaux'],
        ]);
    }

    /**
     * Dossier de l'élève, ouvert au besoin depuis la grille tarifaire.
     *
     * Recherche sur `Tenant::schoolIds()` (pas `schoolId()`) : un super admin
     * en mode agrégé (sans X-School-Id) doit pouvoir ouvrir le dossier d'un
     * élève de n'importe quelle école du complexe, pas seulement de la
     * première — sans quoi cette route 404 pour tout élève qui ne s'y trouve
     * pas. L'année est ensuite résolue sur l'école propre de l'élève, pas sur
     * celle — ambiguë en mode agrégé — du tenant.
     */
    public function dossier(Request $request, int $eleveId): JsonResponse
    {
        $eleve = Eleve::forSchool(Tenant::schoolIds())->dansPerimetre($request->user())->with('classe')->findOrFail($eleveId);

        $dossier = $this->service->dossier($eleve, $this->annee($request, $eleve->school_id));
        $dossier->load('eleve.classe');
        $dossier->loadMissing(['fraisAnnexes', 'versements' => fn($q) => $q->valides()->with('lignes'), 'busAffectations.trajet']);

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
            'lignes.*.affectation' => ['required_with:lignes', 'in:scolarite,frais_annexe,report_dette'],
            'lignes.*.dossier_frais_annexe_id' => ['nullable', 'integer'],
            'lignes.*.libelle' => ['nullable', 'string', 'max:150'],
            'lignes.*.montant' => ['required_with:lignes', 'integer', 'min:1'],
            'canaux' => ['nullable', 'array'],
            'canaux.*' => ['in:sms,whatsapp,email,interne'],
        ]);

        try {
            $versement = $this->service->encaisser($dossier, $donnees, $request->user()?->id);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        $this->confirmerPaiement($dossier->fresh(['eleve.tuteurs']), $versement, $donnees['canaux'] ?? []);

        return ApiResponse::created(
            ['versement_id' => $versement->id, 'numero_recu' => $versement->numero_recu],
            "Encaissement enregistré — reçu {$versement->numero_recu}.",
        );
    }

    /**
     * Confirmation du paiement au tuteur principal de l'élève, sur les
     * canaux choisis au comptoir — un échec d'envoi ne remet jamais en cause
     * l'encaissement, déjà enregistré.
     *
     * @param  list<string>  $canaux
     */
    private function confirmerPaiement(DossierScolarite $dossier, Versement $versement, array $canaux): void
    {
        $tuteur = $dossier->eleve->tuteurs->firstWhere('pivot.is_principal', true)
            ?? $dossier->eleve->tuteurs->first();

        $reste = $dossier->fresh()?->reste_a_payer ?? 0;
        $message = "Paiement de {$this->francs($versement->montant)} reçu pour {$dossier->eleve->nom_complet} (reçu {$versement->numero_recu}). "
            . ($reste > 0 ? "Reste à payer : {$this->francs($reste)}." : 'Scolarité soldée.');

        $this->notifications->notifier($tuteur, $canaux, $dossier->school_id, 'Confirmation de paiement', $message);
    }

    private function francs(int $montant): string
    {
        return number_format($montant, 0, ',', ' ') . ' F';
    }

    public function annuler(Request $request, int $versementId): JsonResponse
    {
        $versement = Versement::forSchool(Tenant::schoolIds())->findOrFail($versementId);

        $donnees = $request->validate(['motif' => ['required', 'string', 'min:3', 'max:255']]);

        try {
            $this->service->annuler($versement, $donnees['motif'], $request->user()?->id);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(null, "Reçu {$versement->numero_recu} annulé.");
    }

    /**
     * Versements suspects d'avoir été encaissés plusieurs fois pour le même
     * élève : même dossier, même montant — peu importe la date ou le mode,
     * une ressaisie pouvant survenir un autre jour. Regroupés pour
     * vérification manuelle avant annulation — un versement ne se supprime
     * jamais.
     */
    public function versementsDoublons(): JsonResponse
    {
        $versements = $this->versementsCandidatsDoublons(Tenant::schoolIds())
            ->with(['dossier.eleve.classe', 'encaisseur:id,name'])
            ->orderBy('versements.date_versement')
            ->orderBy('versements.id')
            ->get();

        $groupes = $versements
            ->groupBy(fn(Versement $v) => "{$v->dossier_scolarite_id}:{$v->montant}")
            ->values()
            ->map(function ($groupe) {
                $premier = $groupe->first();
                $dossier = $premier->dossier;

                return [
                    'dossier_id' => $dossier->id,
                    'eleve' => [
                        'id' => $dossier->eleve->id,
                        'nom_complet' => $dossier->eleve->nom_complet,
                        'matricule' => $dossier->eleve->matricule,
                        'classe' => $dossier->eleve->classe?->nom,
                    ],
                    'montant' => $premier->montant,
                    'versements' => $groupe->map(fn(Versement $v) => [
                        'id' => $v->id,
                        'numero_recu' => $v->numero_recu,
                        'date_versement' => $v->date_versement->toDateString(),
                        'mode' => $v->mode,
                        'reference_externe' => $v->reference_externe,
                        'note' => $v->note,
                        'encaisse_par' => $v->encaisseur?->name,
                        'cree_le' => $v->created_at?->toIso8601String(),
                    ])->values(),
                ];
            });

        return ApiResponse::success([
            'groupes' => $groupes,
            'total_montant' => $groupes->sum(fn($g) => $g['montant'] * (count($g['versements']) - 1)),
        ]);
    }

    /**
     * Annule automatiquement le doublon le plus récent d'une paire de
     * versements identiques encaissés à quelques minutes d'intervalle — le
     * signe d'un double clic ou d'une double saisie au comptoir. Les
     * groupes plus ambigus (plus de deux versements, ou écart de temps
     * important) restent à traiter manuellement.
     */
    public function versementsDoublonsTraitementAutomatique(Request $request): JsonResponse
    {
        $versements = $this->versementsCandidatsDoublons(Tenant::schoolIds())->orderBy('versements.id')->get();

        $groupes = $versements
            ->groupBy(fn(Versement $v) => "{$v->dossier_scolarite_id}:{$v->montant}")
            ->filter(fn($groupe) => $groupe->count() === 2);

        $annules = 0;
        $montantAnnule = 0;
        $details = [];

        foreach ($groupes as $groupe) {
            [$premier, $second] = $groupe->sortBy('id')->values()->all();
            if ($premier->created_at->diffInMinutes($second->created_at) > 30) continue;

            $this->service->annuler(
                $second,
                'Doublon de paiement détecté automatiquement : même élève, même montant, encaissé deux fois à quelques minutes d\'intervalle.',
                $request->user()?->id,
            );
            $annules++;
            $montantAnnule += $second->montant;
            $details[] = ['conserve_id' => $premier->id, 'annule_id' => $second->id, 'montant' => $second->montant];
        }

        return ApiResponse::success(
            ['annules' => $annules, 'montant_annule' => $montantAnnule, 'details' => $details],
            "{$annules} doublon(s) de paiement annulé(s) automatiquement.",
        );
    }

    /** Reçu au format du ticket de caisse (rouleau 80 mm). */
    public function recu(int $versementId): Response
    {
        $versement = Versement::forSchool(Tenant::schoolIds())->findOrFail($versementId);

        return response((new RecuVersementGenerator)->build($versement), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="recu-' . Str::slug($versement->numero_recu) . '.pdf"',
        ]);
    }

    private function dossierDuTenant(int $id): DossierScolarite
    {
        return DossierScolarite::forSchool(Tenant::schoolIds())->avecTotaux()->findOrFail($id);
    }

    /**
     * Ne charge que les versements appartenant à une paire (dossier, montant)
     * en doublon — repérée par une agrégation en base — plutôt que tout
     * l'historique des encaissements de l'école, qui grossit indéfiniment
     * d'année en année alors que les doublons eux-mêmes restent une poignée
     * de paires.
     *
     * @param  list<int>  $schoolIds
     */
    private function versementsCandidatsDoublons(array $schoolIds): Builder
    {
        $paires = Versement::forSchool($schoolIds)
            ->valides()
            ->select('dossier_scolarite_id', 'montant')
            ->groupBy('dossier_scolarite_id', 'montant')
            ->havingRaw('COUNT(*) > 1');

        return Versement::forSchool($schoolIds)
            ->valides()
            ->joinSub($paires, 'doublons', function ($join) {
                $join->on('versements.dossier_scolarite_id', '=', 'doublons.dossier_scolarite_id')
                    ->on('versements.montant', '=', 'doublons.montant');
            })
            ->select('versements.*');
    }

    /**
     * Année visée : celle passée en paramètre, sinon l'année active de
     * l'école. Une situation financière n'a aucun sens hors d'une année
     * précise.
     *
     * `$schoolId` se précise quand l'appelant connaît déjà l'école exacte du
     * dossier consulté (ex. celle de l'élève) ; à défaut, celle du tenant —
     * ambiguë en mode agrégé, mais alors sans meilleure alternative.
     */
    private function annee(Request $request, ?int $schoolId = null): AnneeScolaire
    {
        $schoolId ??= app('tenant.school_id');

        if ($id = $request->integer('annee_scolaire_id')) {
            return AnneeScolaire::where('school_id', $schoolId)->findOrFail($id);
        }

        return AnneeScolaire::where('school_id', $schoolId)->where('is_active', true)->firstOrFail();
    }
}
