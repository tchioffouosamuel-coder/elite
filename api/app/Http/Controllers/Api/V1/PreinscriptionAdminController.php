<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Eleve;
use App\Models\Preinscription;
use App\Services\PreinscriptionService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** File d'attente des préinscriptions déposées par les parents, à valider ou rejeter. */
class PreinscriptionAdminController extends Controller
{
    public function __construct(private readonly PreinscriptionService $service) {}

    public function index(Request $request): JsonResponse
    {
        $preinscriptions = Preinscription::forSchool(Tenant::schoolIds())
            ->with(['tuteur:id,nom_complet,telephone,email', 'eleve:id,nom_complet,matricule'])
            ->when($request->string('statut')->toString(), fn ($q, $s) => $q->where('statut', $s))
            ->latest()
            ->get();

        return ApiResponse::success($preinscriptions->map(fn (Preinscription $p) => $this->resume($p)));
    }

    public function show(int $id): JsonResponse
    {
        $p = Preinscription::forSchool(Tenant::schoolIds())
            ->with(['tuteur:id,nom_complet,telephone,email', 'eleve:id,nom_complet,matricule,classe_id', 'eleve.classe:id,nom'])
            ->findOrFail($id);

        return ApiResponse::success([
            ...$this->resume($p),
            'donnees_eleve' => $p->donnees_eleve,
            'donnees_tuteurs' => $p->donnees_tuteurs,
            'note_admin' => $p->note_admin,
            'mode_versement' => $p->mode_versement,
            'reference_externe' => $p->reference_externe,
            'rubriques_versement' => $p->rubriques_versement,
            'classe_actuelle' => $p->eleve?->classe?->nom,
        ]);
    }

    /**
     * Préinscription créée par l'admin lui-même, pour un élève déjà connu du
     * système (réinscription au guichet) — saisie et validée du même geste,
     * sans passer par la file d'attente « en attente ». Cf.
     * `PreinscriptionService::creerEtValiderParAdmin()`.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'eleve_id' => ['required', 'integer'],

            'donnees_eleve.nom_complet' => ['required', 'string', 'max:150'],
            'donnees_eleve.sexe' => ['required', 'in:M,F'],
            'donnees_eleve.date_naissance' => ['required', 'date'],
            'donnees_eleve.lieu_naissance' => ['nullable', 'string', 'max:150'],
            'donnees_eleve.adresse' => ['nullable', 'string', 'max:255'],
            'donnees_eleve.numero_acte_naissance' => ['nullable', 'string', 'max:100'],
            'donnees_eleve.lieu_delivrance_acte' => ['nullable', 'string', 'max:150'],
            'donnees_eleve.officier_etat_civil' => ['nullable', 'string', 'max:150'],
            'donnees_eleve.groupe_sanguin' => ['nullable', 'string', 'max:10'],
            'donnees_eleve.situation_sanitaire' => ['nullable', 'string', 'max:1000'],
            'donnees_eleve.aptitude' => ['nullable', 'in:apte,inapte'],
            'donnees_eleve.allergies' => ['nullable', 'string', 'max:1000'],

            'donnees_tuteurs' => ['required', 'array', 'min:1'],
            'donnees_tuteurs.*.nom_complet' => ['required', 'string', 'max:150'],
            'donnees_tuteurs.*.telephone' => ['nullable', 'string', 'max:30'],
            'donnees_tuteurs.*.email' => ['nullable', 'email', 'max:150'],
            'donnees_tuteurs.*.profession' => ['nullable', 'string', 'max:150'],
            'donnees_tuteurs.*.lieu_service' => ['nullable', 'string', 'max:150'],
            'donnees_tuteurs.*.adresse' => ['nullable', 'string', 'max:255'],
            'donnees_tuteurs.*.lien_parente' => ['nullable', 'string', 'max:50'],
            'donnees_tuteurs.*.is_principal' => ['nullable', 'boolean'],

            'montant_verser' => ['nullable', 'integer', 'min:1'],
            'mode_versement' => ['nullable', 'in:especes,mobile_money,virement,cheque,depot_bancaire'],
            'reference_externe' => ['nullable', 'string', 'max:100'],
            'rubriques_versement' => ['nullable', 'array', 'min:1'],
            'rubriques_versement.*.affectation' => ['required_with:rubriques_versement', 'in:scolarite,frais_annexe,report_dette'],
            'rubriques_versement.*.dossier_frais_annexe_id' => ['nullable', 'integer'],
            'rubriques_versement.*.libelle' => ['nullable', 'string', 'max:150'],
            'rubriques_versement.*.montant' => ['required_with:rubriques_versement', 'integer', 'min:1'],
        ]);

        $eleve = Eleve::forSchool(Tenant::schoolIds())->findOrFail($data['eleve_id']);

        try {
            $p = $this->service->creerEtValiderParAdmin($eleve, $data, $request->user()->id);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::created(
            $this->resume($p->load('eleve:id,nom_complet,matricule')),
            'Préinscription enregistrée et validée.'
        );
    }

    /** Corrige les informations proposées par le parent avant validation (coquille, champ oublié…). */
    public function update(Request $request, int $id): JsonResponse
    {
        $p = Preinscription::forSchool(Tenant::schoolIds())->findOrFail($id);

        $data = $request->validate([
            'donnees_eleve.nom_complet' => ['required', 'string', 'max:150'],
            'donnees_eleve.sexe' => ['required', 'in:M,F'],
            'donnees_eleve.date_naissance' => ['required', 'date'],
            'donnees_eleve.lieu_naissance' => ['nullable', 'string', 'max:150'],
            'donnees_eleve.adresse' => ['nullable', 'string', 'max:255'],
            'donnees_eleve.numero_acte_naissance' => ['nullable', 'string', 'max:100'],
            'donnees_eleve.lieu_delivrance_acte' => ['nullable', 'string', 'max:150'],
            'donnees_eleve.officier_etat_civil' => ['nullable', 'string', 'max:150'],
            'donnees_eleve.groupe_sanguin' => ['nullable', 'string', 'max:10'],
            'donnees_eleve.situation_sanitaire' => ['nullable', 'string', 'max:1000'],
            'donnees_eleve.aptitude' => ['nullable', 'in:apte,inapte'],
            'donnees_eleve.allergies' => ['nullable', 'string', 'max:1000'],

            'donnees_tuteurs' => ['required', 'array', 'min:1'],
            'donnees_tuteurs.*.nom_complet' => ['required', 'string', 'max:150'],
            'donnees_tuteurs.*.telephone' => ['nullable', 'string', 'max:30'],
            'donnees_tuteurs.*.email' => ['nullable', 'email', 'max:150'],
            'donnees_tuteurs.*.profession' => ['nullable', 'string', 'max:150'],
            'donnees_tuteurs.*.lieu_service' => ['nullable', 'string', 'max:150'],
            'donnees_tuteurs.*.adresse' => ['nullable', 'string', 'max:255'],
            'donnees_tuteurs.*.lien_parente' => ['nullable', 'string', 'max:50'],
            'donnees_tuteurs.*.is_principal' => ['nullable', 'boolean'],
        ]);

        try {
            $p = $this->service->modifierDonnees($p, $data['donnees_eleve'], $data['donnees_tuteurs']);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success([
            ...$this->resume($p),
            'donnees_eleve' => $p->donnees_eleve,
            'donnees_tuteurs' => $p->donnees_tuteurs,
        ], 'Informations mises à jour.');
    }

    public function valider(Request $request, int $id): JsonResponse
    {
        $p = Preinscription::forSchool(Tenant::schoolIds())->findOrFail($id);

        try {
            $p = $this->service->valider($p, $request->user()?->id);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($this->resume($p->load('eleve:id,nom_complet,matricule')), 'Préinscription validée.');
    }

    public function rejeter(Request $request, int $id): JsonResponse
    {
        $p = Preinscription::forSchool(Tenant::schoolIds())->findOrFail($id);
        $data = $request->validate(['motif' => ['required', 'string', 'min:3', 'max:255']]);

        try {
            $p = $this->service->rejeter($p, $data['motif'], $request->user()?->id);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($this->resume($p), 'Préinscription rejetée.');
    }

    private function resume(Preinscription $p): array
    {
        return [
            'id' => $p->id,
            'type' => $p->type,
            'statut' => $p->statut,
            'tuteur' => $p->tuteur ? ['id' => $p->tuteur->id, 'nom_complet' => $p->tuteur->nom_complet, 'telephone' => $p->tuteur->telephone, 'email' => $p->tuteur->email] : null,
            'eleve' => $p->eleve ? ['id' => $p->eleve->id, 'nom_complet' => $p->eleve->nom_complet, 'matricule' => $p->eleve->matricule] : null,
            'nom_propose' => $p->donnees_eleve['nom_complet'] ?? null,
            'montant_verser' => $p->montant_verser,
            'versement_id' => $p->versement_id,
            'motif_rejet' => $p->motif_rejet,
            'created_at' => $p->created_at->format('Y-m-d H:i'),
            'traite_le' => $p->traite_le?->format('Y-m-d H:i'),
        ];
    }
}
