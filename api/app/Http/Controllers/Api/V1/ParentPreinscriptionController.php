<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\Preinscription;
use App\Models\School;
use App\Models\Tuteur;
use App\Services\PreinscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ParentPreinscriptionController extends Controller
{
    public function __construct(private readonly PreinscriptionService $service) {}

    /** Historique des préinscriptions déposées par le compte connecté, la plus récente en tête. */
    public function index(Request $request): JsonResponse
    {
        $tuteurIds = Tuteur::where('user_id', $request->user()->id)->pluck('id');

        $preinscriptions = Preinscription::whereIn('tuteur_id', $tuteurIds)
            ->with('eleve:id,nom_complet')
            ->latest()
            ->get()
            ->map(fn (Preinscription $p) => $this->resume($p));

        return ApiResponse::success($preinscriptions);
    }

    /** Détail d'une préinscription du compte connecté — pour préremplir le formulaire d'édition tant qu'elle est en attente. */
    public function show(Request $request, int $id): JsonResponse
    {
        $tuteurIds = Tuteur::where('user_id', $request->user()->id)->pluck('id');
        $preinscription = Preinscription::whereIn('tuteur_id', $tuteurIds)->findOrFail($id);

        return ApiResponse::success([
            ...$this->resume($preinscription),
            'donnees_eleve' => $preinscription->donnees_eleve,
            'donnees_tuteurs' => $preinscription->donnees_tuteurs,
            'note_admin' => $preinscription->note_admin,
            'mode_versement' => $preinscription->mode_versement,
            'reference_externe' => $preinscription->reference_externe,
        ]);
    }

    /**
     * Corrige une préinscription déposée par le compte connecté, tant
     * qu'elle n'a pas été traitée — évite d'avoir à en redéposer une
     * nouvelle (refusé par {@see PreinscriptionService::soumettre()}) pour
     * la moindre coquille.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $tuteurIds = Tuteur::where('user_id', $request->user()->id)->pluck('id');
        $preinscription = Preinscription::whereIn('tuteur_id', $tuteurIds)->findOrFail($id);

        $data = $request->validate([
            'donnees_eleve.nom_complet' => ['required', 'string', 'max:150'],
            'donnees_eleve.sexe' => ['required', 'in:M,F'],
            'donnees_eleve.date_naissance' => ['required', 'date'],
            'donnees_eleve.lieu_naissance' => ['nullable', 'string', 'max:150'],
            'donnees_eleve.adresse' => ['nullable', 'string', 'max:255'],
            'donnees_eleve.classe_id' => ['nullable', 'integer', 'exists:classes,id'],
            'donnees_eleve.numero_acte_naissance' => ['nullable', 'string', 'max:100'],
            'donnees_eleve.lieu_delivrance_acte' => ['nullable', 'string', 'max:150'],
            'donnees_eleve.officier_etat_civil' => ['nullable', 'string', 'max:150'],
            'donnees_eleve.groupe_sanguin' => ['nullable', 'string', 'max:10'],
            'donnees_eleve.situation_sanitaire' => ['nullable', 'string', 'max:1000'],
            'donnees_eleve.aptitude' => ['nullable', 'in:apte,inapte'],
            'donnees_eleve.allergies' => ['nullable', 'string', 'max:1000'],
            'donnees_eleve.photo_tenue_path' => ['nullable', 'string'],

            'donnees_tuteurs' => ['required', 'array', 'min:1'],
            'donnees_tuteurs.*.nom_complet' => ['required', 'string', 'max:150'],
            'donnees_tuteurs.*.telephone' => ['nullable', 'string', 'max:30'],
            'donnees_tuteurs.*.email' => ['nullable', 'email', 'max:150'],
            'donnees_tuteurs.*.profession' => ['nullable', 'string', 'max:150'],
            'donnees_tuteurs.*.lieu_service' => ['nullable', 'string', 'max:150'],
            'donnees_tuteurs.*.adresse' => ['nullable', 'string', 'max:255'],
            'donnees_tuteurs.*.lien_parente' => ['nullable', 'string', 'max:50'],
            'donnees_tuteurs.*.is_principal' => ['nullable', 'boolean'],

            'note_admin' => ['nullable', 'string', 'max:1000'],
            'montant_verser' => ['nullable', 'integer', 'min:0'],
            'mode_versement' => ['nullable', 'in:especes,mobile_money,virement,cheque,depot_bancaire'],
            'reference_externe' => ['nullable', 'string', 'max:100'],
            'rubriques_versement' => ['nullable', 'array'],
            'rubriques_versement.*.affectation' => ['required_with:rubriques_versement', 'in:scolarite,frais_annexe,report_dette'],
            'rubriques_versement.*.dossier_frais_annexe_id' => ['nullable', 'integer'],
            'rubriques_versement.*.libelle' => ['nullable', 'string', 'max:150'],
            'rubriques_versement.*.montant' => ['required_with:rubriques_versement', 'integer', 'min:1'],
        ]);

        try {
            $preinscription = $this->service->modifierDonnees(
                $preinscription,
                $data['donnees_eleve'],
                $data['donnees_tuteurs'],
                collect($data)->except(['donnees_eleve', 'donnees_tuteurs'])->all(),
            );
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($this->resume($preinscription), 'Préinscription mise à jour.');
    }

    public function store(Request $request): JsonResponse
    {
        $tuteur = Tuteur::where('user_id', $request->user()->id)->first();

        if (! $tuteur) {
            return ApiResponse::error('Aucune fiche tuteur associée à ce compte.', 422);
        }

        $data = $request->validate([
            'type' => ['required', 'in:existant,nouveau'],
            'eleve_id' => ['required_if:type,existant', 'nullable', 'integer'],
            'school_id' => ['required_if:type,nouveau', 'nullable', 'integer', 'exists:schools,id'],

            'donnees_eleve.nom_complet' => ['required', 'string', 'max:150'],
            'donnees_eleve.sexe' => ['required', 'in:M,F'],
            'donnees_eleve.date_naissance' => ['required', 'date'],
            'donnees_eleve.lieu_naissance' => ['nullable', 'string', 'max:150'],
            'donnees_eleve.adresse' => ['nullable', 'string', 'max:255'],
            'donnees_eleve.classe_id' => ['required_if:type,nouveau', 'nullable', 'integer', 'exists:classes,id'],
            'donnees_eleve.numero_acte_naissance' => ['nullable', 'string', 'max:100'],
            'donnees_eleve.lieu_delivrance_acte' => ['nullable', 'string', 'max:150'],
            'donnees_eleve.officier_etat_civil' => ['nullable', 'string', 'max:150'],
            'donnees_eleve.groupe_sanguin' => ['nullable', 'string', 'max:10'],
            'donnees_eleve.situation_sanitaire' => ['nullable', 'string', 'max:1000'],
            'donnees_eleve.aptitude' => ['nullable', 'in:apte,inapte'],
            'donnees_eleve.allergies' => ['nullable', 'string', 'max:1000'],
            'donnees_eleve.photo_tenue_path' => ['nullable', 'string'],

            'donnees_tuteurs' => ['required', 'array', 'min:1'],
            'donnees_tuteurs.*.nom_complet' => ['required', 'string', 'max:150'],
            'donnees_tuteurs.*.telephone' => ['nullable', 'string', 'max:30'],
            'donnees_tuteurs.*.email' => ['nullable', 'email', 'max:150'],
            'donnees_tuteurs.*.profession' => ['nullable', 'string', 'max:150'],
            'donnees_tuteurs.*.lieu_service' => ['nullable', 'string', 'max:150'],
            'donnees_tuteurs.*.adresse' => ['nullable', 'string', 'max:255'],
            'donnees_tuteurs.*.lien_parente' => ['nullable', 'string', 'max:50'],
            'donnees_tuteurs.*.is_principal' => ['nullable', 'boolean'],

            'note_admin' => ['nullable', 'string', 'max:1000'],
            'montant_verser' => ['nullable', 'integer', 'min:0'],
            'mode_versement' => ['nullable', 'in:especes,mobile_money,virement,cheque,depot_bancaire'],
            'reference_externe' => ['nullable', 'string', 'max:100'],
            'rubriques_versement' => ['nullable', 'array'],
            'rubriques_versement.*.affectation' => ['required_with:rubriques_versement', 'in:scolarite,frais_annexe,report_dette'],
            'rubriques_versement.*.dossier_frais_annexe_id' => ['nullable', 'integer'],
            'rubriques_versement.*.libelle' => ['nullable', 'string', 'max:150'],
            'rubriques_versement.*.montant' => ['required_with:rubriques_versement', 'integer', 'min:1'],
        ]);

        try {
            $preinscription = $this->service->soumettre($tuteur, $data);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::created($this->resume($preinscription), 'Préinscription transmise, en attente de validation.');
    }

    /** Écoles du même complexe que le tuteur — pour choisir où inscrire un nouvel enfant. */
    public function ecolesDisponibles(Request $request): JsonResponse
    {
        $tuteur = Tuteur::where('user_id', $request->user()->id)->with('school')->first();

        if (! $tuteur?->school) {
            return ApiResponse::success([]);
        }

        $ecoles = School::where('complexe_id', $tuteur->school->complexe_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'type']);

        return ApiResponse::success($ecoles);
    }

    /** Classes d'une école, pour le choix de placement d'un nouvel enfant — sans exiger `classes.view`, hors de portée d'un parent. */
    public function classesDisponibles(int $schoolId): JsonResponse
    {
        $classes = Classe::where('school_id', $schoolId)->orderBy('nom')->get(['id', 'nom']);

        return ApiResponse::success($classes);
    }

    private function resume(Preinscription $p): array
    {
        return [
            'id' => $p->id,
            'type' => $p->type,
            'statut' => $p->statut,
            'eleve' => $p->eleve ? ['id' => $p->eleve->id, 'nom_complet' => $p->eleve->nom_complet] : ['nom_complet' => $p->donnees_eleve['nom_complet'] ?? null],
            'nom_propose' => $p->donnees_eleve['nom_complet'] ?? null,
            // École visée — surtout utile pour une inscription « nouveau »,
            // fixée au dépôt : la modifier ensuite reviendrait à changer
            // d'établissement en cours de route, donc l'écran de correction
            // se contente de l'afficher.
            'school' => $p->school ? ['id' => $p->school->id, 'name' => $p->school->name] : null,
            'montant_verser' => $p->montant_verser,
            'motif_rejet' => $p->motif_rejet,
            'created_at' => $p->created_at->format('Y-m-d H:i'),
            'traite_le' => $p->traite_le?->format('Y-m-d H:i'),
        ];
    }
}
