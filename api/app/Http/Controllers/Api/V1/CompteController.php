<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ReinitialiserMotDePasseRequest;
use App\Models\ActivityLog;
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
            ->with(['school:id,name,code', 'roles', 'personnel:id,user_id,nom_complet,matricule,fonction_id', 'personnel.fonctionReference'])
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
     * Comptes que le super administrateur peut administrer : ceux du
     * périmètre résolu par le tenant, et — pour qu'un super administrateur ne
     * s'exclue jamais lui-même de sa propre liste — tout compte portant ce
     * rôle, même sans `school_id` (cas du compte racine, rattaché à aucun
     * établissement en particulier).
     */
    private function comptesAccessibles(): Builder
    {
        return User::where(
            fn ($q) => $q->whereIn('school_id', Tenant::schoolIds())
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
            'derniere_connexion' => $derniere ? Carbon::parse($derniere)->toISOString() : null,
            'cree_le' => $u->created_at?->toISOString(),
        ];
    }
}
