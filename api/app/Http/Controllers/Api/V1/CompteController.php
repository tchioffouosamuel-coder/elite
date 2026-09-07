<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ReinitialiserMotDePasseRequest;
use App\Models\ActivityLog;
use App\Models\School;
use App\Models\Tuteur;
use App\Models\User;
use App\Services\AuthService;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Administration des comptes utilisateurs, réservée au super administrateur.
 *
 * Distincte de la gestion du personnel ou des tuteurs, qui porte sur la
 * fiche : ici, c'est le compte de connexion lui-même qui est en jeu — accès,
 * mot de passe, activité — quel que soit le type de fiche qu'il représente.
 */
class CompteController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    /** Tous les comptes du périmètre accessible, personnel comme parents. */
    public function index(): JsonResponse
    {
        $comptes = $this->comptesAccessibles()
            ->with(['school:id,name,code', 'schools:id,name,code', 'roles', 'personnel:id,user_id,nom_complet,matricule,fonction_id', 'personnel.fonctionReference'])
            ->orderBy('name')
            ->get();

        $tuteursParUser = Tuteur::whereIn('user_id', $comptes->pluck('id')->filter())
            ->pluck('nom_complet', 'user_id');

        $dernieresConnexions = ActivityLog::whereIn('user_id', $comptes->pluck('id'))
            ->where('action', 'connexion')
            ->selectRaw('user_id, MAX(created_at) as derniere')
            ->groupBy('user_id')
            ->pluck('derniere', 'user_id');

        return ApiResponse::success(
            $comptes->map(fn (User $u) => $this->presenter($u, $tuteursParUser, $dernieresConnexions))->values(),
        );
    }

    /** Journal d'activité d'un compte précis — ses propres actions, et celles dont il a été la cible. */
    public function activite(Request $request, int $id): JsonResponse
    {
        $compte = $this->comptesAccessibles()->findOrFail($id);

        $journal = ActivityLog::where(
            fn ($q) => $q->where('user_id', $compte->id)
                ->orWhere(fn ($q2) => $q2->where('subject_type', User::class)->where('subject_id', $compte->id))
        )
            ->orderByDesc('created_at')
            ->paginate(30);

        return ApiResponse::paginated($journal);
    }

    /**
     * Fixe un nouveau mot de passe pour ce compte, sans connaître l'actuel —
     * l'intérêt même de la fonction quand son titulaire ne peut plus se
     * connecter. Cf. {@see AuthService::reinitialiserMotDePasse()}.
     */
    public function reinitialiserMotDePasse(ReinitialiserMotDePasseRequest $request, int $id): JsonResponse
    {
        $compte = $this->comptesAccessibles()->findOrFail($id);

        $this->authService->reinitialiserMotDePasse($compte, $request->string('nouveau_mot_de_passe')->toString());

        ActivityLog::enregistrer(
            $request->user(),
            'reinitialisation_mot_de_passe',
            "Mot de passe réinitialisé pour {$compte->name}.",
            $compte,
        );

        return ApiResponse::success(message: 'Mot de passe réinitialisé.');
    }

    /**
     * Attribue à ce compte l'accès à des écoles supplémentaires, en plus de
     * son école principale — pour un compte de direction transverse
     * (« Directrice Primaire et Maternelle », chauffeur/infirmier/vendeur
     * des deux écoles). Cf. {@see \App\Models\User::ecolesAccessibles()}.
     *
     * Restreint aux écoles du même complexe que l'école principale : ouvrir
     * l'accès à un autre complexe n'a pas de sens métier et court-circuiterait
     * le périmètre du super admin lui-même.
     *
     * Le compte racine (super admin sans `school_id`, cf. `comptesAccessibles()`)
     * n'a justement aucune école principale à comparer : lui appliquer cette
     * restriction l'empêchait de s'attribuer la moindre école, la requête
     * `complexe_id = null` ne correspondant à aucun établissement. Un tel
     * compte n'étant scopé à aucun complexe en particulier, il peut recevoir
     * l'accès à n'importe quelle école existante.
     */
    public function attribuerEcoles(Request $request, int $id): JsonResponse
    {
        $compte = $this->comptesAccessibles()->findOrFail($id);

        $complexeId = $compte->school?->complexe_id;

        $data = $request->validate([
            'school_ids' => ['array'],
            'school_ids.*' => [
                'integer',
                function ($attribut, $valeur, $fail) use ($complexeId) {
                    $ecole = School::find($valeur);

                    if ($ecole === null) {
                        $fail("L'établissement {$valeur} n'existe pas.");
                    } elseif ($complexeId !== null && $ecole->complexe_id !== $complexeId) {
                        $fail("L'établissement {$valeur} n'appartient pas au même complexe que l'école principale du compte.");
                    }
                },
            ],
        ]);

        // L'école principale reste gérée par la fiche personnel/le compte
        // lui-même (`school_id`), jamais dupliquée dans la pivot.
        $ecoles = collect($data['school_ids'] ?? [])->reject(fn ($id) => $id === $compte->school_id)->values();

        $compte->schools()->sync($ecoles);

        ActivityLog::enregistrer(
            $request->user(),
            'attribution_ecoles',
            "Écoles accessibles mises à jour pour {$compte->name}.",
            $compte,
        );

        return ApiResponse::success(
            $compte->fresh()->ecolesAccessibles()->map(fn ($ecole) => ['id' => $ecole->id, 'name' => $ecole->name])->values(),
            'Écoles accessibles mises à jour.',
        );
    }

    /**
     * Bloque ce compte : `is_active = false` empêche toute connexion future
     * (cf. `AuthService::login()`), sans toucher à la fiche personnel/tuteur
     * qu'il représente — l'agent ou le parent reste dans les effectifs,
     * seul son accès est coupé. Réversible via {@see debloquer()}.
     */
    public function bloquer(Request $request, int $id): JsonResponse
    {
        $compte = $this->comptesAccessibles()->findOrFail($id);

        if ($compte->id === $request->user()->id) {
            return ApiResponse::error('Impossible de bloquer votre propre compte.', 422);
        }

        $compte->update(['is_active' => false]);

        ActivityLog::enregistrer($request->user(), 'blocage_compte', "Compte de {$compte->name} bloqué.", $compte);

        return ApiResponse::success(message: 'Compte bloqué.');
    }

    public function debloquer(Request $request, int $id): JsonResponse
    {
        $compte = $this->comptesAccessibles()->findOrFail($id);

        $compte->update(['is_active' => true]);

        ActivityLog::enregistrer($request->user(), 'deblocage_compte', "Compte de {$compte->name} débloqué.", $compte);

        return ApiResponse::success(message: 'Compte débloqué.');
    }

    /**
     * Supprime définitivement ce compte de connexion — pas la fiche qu'il
     * représente. Les tables qui en dépendent directement (jetons d'accès,
     * provisioning desktop, notifications...) sont supprimées en cascade par
     * la base ; la fiche personnel/tuteur/élève, elle, ne fait que perdre sa
     * référence (`user_id` remis à `null`, cf. migrations correspondantes) et
     * reste intacte — seul l'accès disparaît, l'administration pourra en
     * rouvrir un nouveau plus tard si besoin.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $compte = $this->comptesAccessibles()->findOrFail($id);

        if ($compte->id === $request->user()->id) {
            return ApiResponse::error('Impossible de supprimer votre propre compte.', 422);
        }

        $nom = $compte->name;
        $compte->delete();

        ActivityLog::enregistrer($request->user(), 'suppression_compte', "Compte de {$nom} supprimé.");

        return ApiResponse::success(message: 'Compte supprimé.');
    }

    /**
     * Comptes que le super administrateur peut administrer : ceux dont
     * l'école principale OU une école supplémentaire (compte de direction
     * transverse, cf. `attribuerEcoles()`) est dans le périmètre résolu par
     * le tenant, et — pour qu'un super administrateur ne s'exclue jamais
     * lui-même de sa propre liste — tout compte portant ce rôle, même sans
     * `school_id` (cas du compte racine, rattaché à aucun établissement en
     * particulier).
     */
    private function comptesAccessibles(): Builder
    {
        return User::where(
            fn ($q) => $q->whereIn('school_id', Tenant::schoolIds())
                ->orWhereHas('schools', fn ($s) => $s->whereIn('schools.id', Tenant::schoolIds()))
                ->orWhereHas('roles', fn ($r) => $r->where('name', 'super_admin')),
        );
    }

    /** @param  Collection<int, string>  $tuteursParUser
     * @param  Collection<int, string>  $dernieresConnexions */
    private function presenter(User $u, Collection $tuteursParUser, Collection $dernieresConnexions): array
    {
        $type = match (true) {
            $u->estSuperAdmin() => 'super_admin',
            $u->personnel !== null => 'personnel',
            $tuteursParUser->has($u->id) => 'parent',
            default => 'autre',
        };

        $derniere = $dernieresConnexions->get($u->id);

        return [
            'id' => $u->id,
            'nom' => $u->name,
            'email' => $u->email,
            'phone' => $u->phone,
            'est_actif' => $u->is_active,
            'doit_changer_mot_de_passe' => $u->doit_changer_mot_de_passe,
            'type' => $type,
            'role' => $u->libelleRole(),
            'matricule' => $u->personnel?->matricule,
            'school' => $u->school ? ['id' => $u->school->id, 'name' => $u->school->name] : null,
            // Écoles supplémentaires (compte de direction transverse, cf.
            // attribuerEcoles()) — l'école principale ci-dessus n'y figure
            // pas, l'écran de gestion des comptes les affiche séparément.
            'ecoles_supplementaires' => $u->schools->map(fn ($e) => ['id' => $e->id, 'name' => $e->name])->values(),
            'derniere_connexion' => $derniere ? Carbon::parse($derniere)->toISOString() : null,
            'cree_le' => $u->created_at?->toISOString(),
        ];
    }
}
