<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TuteurResource;
use App\Models\School;
use App\Models\Setting;
use App\Models\Tuteur;
use App\Services\CompteParentService;
use App\Services\SettingsCatalog;
use App\Support\Pdf\IdentifiantsGenerator;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class TuteurController extends Controller
{
    public function __construct(private readonly CompteParentService $service) {}

    /** Tuteurs de l'école, avec leurs enfants et l'état de leur accès parent — la vue qui remplace de chercher fiche élève par fiche élève. */
    public function index(Request $request): JsonResponse
    {
        $tuteurs = Tuteur::forSchool(Tenant::schoolIds())
            ->with(['eleves:id,nom_complet', 'user:id,is_active'])
            ->when($request->boolean('sans_compte'), fn($q) => $q->whereNull('user_id'))
            ->when($request->string('search')->toString(), fn($q, $s) => $q->where(fn($qq) => $qq
                ->where('nom_complet', 'like', "%{$s}%")
                ->orWhere('telephone', 'like', "%{$s}%")))
            ->orderBy('nom_complet')
            ->paginate((int) $request->integer('per_page', 50));

        $tuteurs->getCollection()->transform(fn(Tuteur $t) => [
            'id' => $t->id,
            'nom_complet' => $t->nom_complet,
            'telephone' => $t->telephone,
            'email' => $t->email,
            'a_compte' => $t->user_id !== null,
            'acces_bloque' => $t->user?->is_active === false,
            'enfants' => $t->eleves->map(fn($e) => ['id' => $e->id, 'nom_complet' => $e->nom_complet])->values(),
        ]);

        return ApiResponse::paginated($tuteurs);
    }

    /**
     * Typeahead utilisé à l'inscription d'un élève : dès que le secrétariat
     * saisit un nom de tuteur, propose les fiches déjà connues de l'école
     * pour éviter les doublons (même parent ressaisi pour un frère/une sœur).
     * Non paginée et bornée à quelques résultats — une recherche d'appoint
     * pendant la frappe, pas un annuaire complet.
     */
    public function recherche(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $mots = preg_split('/\s+/', trim($data['q']), -1, PREG_SPLIT_NO_EMPTY);

        $tuteurs = Tuteur::forSchool(Tenant::schoolIds())
            ->with('telephones')
            ->where(function ($query) use ($mots) {
                foreach ($mots as $mot) {
                    $query->where('nom_complet', 'like', "%{$mot}%");
                }
            })
            ->orderBy('nom_complet')
            ->limit(8)
            ->get();

        return ApiResponse::success(TuteurResource::collection($tuteurs));
    }

    /**
     * Ouvre l'accès au portail parent pour ce tuteur, ou renvoie son compte
     * s'il en a déjà un — idempotent, comme l'ouverture des comptes agent.
     */
    public function creerCompteParent(int $id): JsonResponse
    {
        $tuteur = Tuteur::forSchool(Tenant::schoolIds())->findOrFail($id);

        try {
            $user = $this->service->assurer($tuteur);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success([
            'user_id' => $user->id,
            'identifiant' => $user->phone,
            'mot_de_passe_provisoire' => $user->doit_changer_mot_de_passe
                ? Setting::get($tuteur->school_id, 'mot_de_passe_defaut', SettingsCatalog::default('mot_de_passe_defaut'))
                : null,
        ], 'Accès parent ouvert.');
    }

    /**
     * Ouvre l'accès de tous les tuteurs de l'école qui n'en ont pas encore —
     * le rattrapage pour les familles inscrites avant le portail. Traite tout
     * en une requête : correct pour un petit effectif, mais `Hash::make()`
     * (bcrypt, délibérément coûteux) sur plusieurs centaines de tuteurs
     * dépasse facilement le délai d'exécution du serveur — cf.
     * `comptesParentLotPreparer()`/`comptesParentLotTraiter()` pour le
     * découpage en lots qu'utilise désormais l'écran.
     */
    public function creerComptesParentLot(Request $request): JsonResponse
    {
        $schoolIds = $this->perimetreLot($request);

        $resultat = $this->service->assurerLot($schoolIds);

        $message = $resultat['crees'] > 0
            ? "{$resultat['crees']} accès parent ouvert(s)."
            : 'Aucun nouvel accès à ouvrir — tous les tuteurs avec un numéro valide en ont déjà un.';

        return ApiResponse::success($resultat, $message);
    }

    /** Liste stable des tuteurs sans accès, à découper en lots côté client avant d'appeler `comptesParentLotTraiter()`. */
    public function comptesParentLotPreparer(Request $request): JsonResponse
    {
        $ids = $this->service->tuteursSansCompte($this->perimetreLot($request));

        return ApiResponse::success(['ids' => $ids]);
    }

    /**
     * Ouvre l'accès de ce seul lot d'identifiants — revalidés dans le
     * périmètre courant plutôt que de faire confiance à ce que renvoie le
     * client, au cas où l'école active aurait changé entre-temps.
     */
    public function comptesParentLotTraiter(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $idsAutorises = Tuteur::forSchool(Tenant::schoolIds())->whereIn('id', $data['ids'])->pluck('id')->all();

        return ApiResponse::success($this->service->assurerChunk($idsAutorises));
    }

    /**
     * Le périmètre du rattrapage est celui de la liste affichée : `index()`
     * agrège déjà tout le complexe pour un super admin en mode « Toutes les
     * écoles », le bouton « ouvrir tous les accès manquants » ne peut donc
     * pas exiger une école unique — il refuserait précisément le lot le
     * plus utile. Un `school_id` explicite reste accepté pour le restreindre.
     *
     * @return int|list<int>
     */
    private function perimetreLot(Request $request): int|array
    {
        $demandee = $request->integer('school_id') ?: null;

        return $demandee !== null
            ? [Tenant::resolveWriteSchoolId($demandee)]
            : Tenant::schoolIds();
    }

    public function basculerAcces(int $id): JsonResponse
    {
        $tuteur = Tuteur::forSchool(Tenant::schoolIds())->with('user')->findOrFail($id);

        if (! $tuteur->user) {
            return ApiResponse::error("Ce tuteur n'a pas encore de compte parent.", 422);
        }

        $tuteur->user->update(['is_active' => ! $tuteur->user->is_active]);

        return ApiResponse::success(null, $tuteur->user->is_active ? 'Accès parent débloqué.' : 'Accès parent bloqué.');
    }

    public function supprimerCompteParent(int $id): JsonResponse
    {
        $tuteur = Tuteur::forSchool(Tenant::schoolIds())->with('user')->findOrFail($id);

        if ($tuteur->user) {
            $tuteur->user->delete();
            $tuteur->forceFill(['user_id' => null])->save();
        }

        return ApiResponse::success(null, 'Compte parent supprimé.');
    }

    /**
     * Supprime définitivement la fiche du tuteur — pas seulement son accès au
     * portail (cf. {@see supprimerCompteParent()}) : son compte parent s'il en
     * a un, son lien avec ses enfants, et son historique propre (justifications
     * d'absence, préinscriptions, demandes de modification qu'il a portées)
     * partent avec, par cascade en base. Les fiches des enfants eux-mêmes ne
     * sont jamais touchées.
     */
    public function destroy(int $id): JsonResponse
    {
        $tuteur = Tuteur::forSchool(Tenant::schoolIds())->with('user')->findOrFail($id);

        if ($tuteur->user) {
            $tuteur->user->delete();
        }
        $tuteur->delete();

        return ApiResponse::success(null, 'Tuteur supprimé.');
    }

    /** Document confidentiel des identifiants parent, à distribuer en main propre — même principe que celui du personnel. */
    public function identifiantsParentPdf(): Response
    {
        $schoolIds = Tenant::schoolIds();
        $schools = School::whereIn('id', $schoolIds)->orderBy('name')->get();

        if (Tenant::isAggregate()) {
            $documents = $schools->map(fn(School $school) => [
                'donnees' => $this->service->identifiants($school->id),
                'school' => $school,
            ])->all();
            $pdf = (new IdentifiantsGenerator)->buildMany($documents);
            $nom = 'identifiants-parents-toutes-les-ecoles';
        } else {
            $school = $schools->firstOrFail();
            $pdf = (new IdentifiantsGenerator)->build($this->service->identifiants($school->id), $school);
            $nom = 'identifiants-parents-' . Str::slug($school->name);
        }

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $nom . '.pdf"',
        ]);
    }
}
