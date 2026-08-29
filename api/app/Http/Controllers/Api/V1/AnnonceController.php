<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AnnonceResource;
use App\Models\Annonce;
use App\Models\FonctionReferentiel;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Annonces de l'établissement : publiées par l'administration, visibles de
 * tout le personnel et reprises dans le rapport hebdomadaire envoyé aux
 * parents (cf. `RapportHebdomadaireParents`).
 */
class AnnonceController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(Request $request): JsonResponse
    {
        $annonces = Annonce::forSchool(Tenant::schoolIds())
            ->with(['publiePar', 'school:id,name,code,type'])
            ->orderByDesc('publiee_le')
            ->paginate(20);

        return ApiResponse::paginated($annonces, AnnonceResource::class);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'titre' => ['required', 'string', 'max:200'],
            'contenu' => ['required', 'string', 'max:2000'],
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'cible_type' => ['nullable', 'string', 'in:tous,fonction,utilisateurs'],
            // `required_if` (et non `required_unless:cible_type,tous`) : un
            // appelant qui n'envoie pas du tout `cible_type` (le formulaire web
            // actuel, par exemple) doit rester équivalent à "tous" plutôt que
            // de se voir exiger un champ `cible` qu'il ne connaît pas.
            'cible' => ['required_if:cible_type,fonction,utilisateurs', 'array'],
            // Des identifiants (fonction_referentiel.id ou users.id selon le
            // type) : toujours numériques, jamais des libellés à interpréter.
            'cible.*' => ['integer'],
        ]);

        $schoolId = Tenant::resolveWriteSchoolId($data['school_id'] ?? null);
        $cibleType = $data['cible_type'] ?? 'tous';
        $cible = $data['cible'] ?? [];

        $annonce = Annonce::create([
            'school_id' => $schoolId,
            'titre' => $data['titre'],
            'contenu' => $data['contenu'],
            'publie_par' => $request->user()->personnel?->id,
            'publiee_le' => now(),
            'cible_type' => $cibleType,
            'cible_data' => $cibleType === 'tous' ? null : $cible,
        ]);

        // Le personnel voit l'annonce immédiatement dans sa cloche ; les
        // parents la découvrent, eux, dans le résumé hebdomadaire par SMS.
        // La permission `annonces.view` reste le garde-fou dans tous les cas :
        // cibler quelqu'un qui n'a pas le droit de voir les annonces ne doit
        // pas le faire sortir du contrôle d'accès.
        $userIds = match ($cibleType) {
            'fonction' => User::where('school_id', $schoolId)
                ->whereHas('personnel', fn ($q) => $q->whereIn('fonction_id', $cible))
                ->permission('annonces.view')->pluck('id'),
            'utilisateurs' => User::where('school_id', $schoolId)->whereIn('id', $cible)->permission('annonces.view')->pluck('id'),
            default => User::where('school_id', $schoolId)->permission('annonces.view')->pluck('id'),
        };

        $this->notifications->notifier(
            $schoolId,
            $userIds,
            'annonce',
            $annonce->titre,
            $annonce->contenu,
        );

        return ApiResponse::created(new AnnonceResource($annonce->load(['publiePar', 'school:id,name,code,type'])), 'Annonce publiée.');
    }

    /**
     * Fonctions de l'établissement, pour le sélecteur de ciblage — délibérément
     * ouvert à `annonces.publish` plutôt que réservé à `personnel.view` : celui
     * qui publie une annonce n'a pas forcément le droit de gérer le personnel.
     */
    public function fonctions(Request $request): JsonResponse
    {
        $schoolId = Tenant::resolveWriteSchoolId($request->integer('school_id') ?: null);

        $fonctions = FonctionReferentiel::forSchool($schoolId)
            ->orderBy('label_fr')
            ->get()
            ->map(fn (FonctionReferentiel $f) => ['id' => $f->id, 'label' => $f->label()]);

        return ApiResponse::success($fonctions);
    }

    /**
     * Recherche de destinataires par nom, pour le ciblage « utilisateurs » —
     * ne remonte que le personnel qui verrait effectivement l'annonce
     * (`annonces.view`), pour ne pas laisser composer une liste qui ne
     * recevra jamais rien.
     */
    public function destinataires(Request $request): JsonResponse
    {
        $schoolId = Tenant::resolveWriteSchoolId($request->integer('school_id') ?: null);
        $recherche = trim((string) $request->string('recherche'));

        $utilisateurs = User::where('school_id', $schoolId)
            ->permission('annonces.view')
            ->with('personnel:id,user_id,nom_complet')
            ->when($recherche !== '', fn ($q) => $q->where(fn ($sub) => $sub
                ->where('name', 'like', "%{$recherche}%")
                ->orWhereHas('personnel', fn ($p) => $p->where('nom_complet', 'like', "%{$recherche}%"))
            ))
            ->limit(20)
            ->get()
            ->map(fn (User $u) => ['id' => $u->id, 'nom_complet' => $u->personnel?->nom_complet ?? $u->name]);

        return ApiResponse::success($utilisateurs);
    }

    public function destroy(int $id): JsonResponse
    {
        $annonce = Annonce::forSchool(Tenant::schoolIds())->findOrFail($id);
        $annonce->delete();

        return ApiResponse::success();
    }
}
