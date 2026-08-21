<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\Tuteur;
use App\Services\CompteParentService;
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
            ->with('eleves:id,nom_complet')
            ->when($request->boolean('sans_compte'), fn ($q) => $q->whereNull('user_id'))
            ->when($request->string('search')->toString(), fn ($q, $s) => $q->where(fn ($qq) => $qq
                ->where('nom_complet', 'like', "%{$s}%")
                ->orWhere('telephone', 'like', "%{$s}%")))
            ->orderBy('nom_complet')
            ->paginate((int) $request->integer('per_page', 50));

        $tuteurs->getCollection()->transform(fn (Tuteur $t) => [
            'id' => $t->id,
            'nom_complet' => $t->nom_complet,
            'telephone' => $t->telephone,
            'email' => $t->email,
            'a_compte' => $t->user_id !== null,
            'enfants' => $t->eleves->map(fn ($e) => ['id' => $e->id, 'nom_complet' => $e->nom_complet])->values(),
        ]);

        return ApiResponse::paginated($tuteurs);
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
            'mot_de_passe_provisoire' => $user->doit_changer_mot_de_passe ? config('personnel.mot_de_passe_defaut') : null,
        ], 'Accès parent ouvert.');
    }

    /** Ouvre l'accès de tous les tuteurs de l'école qui n'en ont pas encore — le rattrapage pour les familles inscrites avant le portail. */
    public function creerComptesParentLot(Request $request): JsonResponse
    {
        $schoolId = Tenant::resolveWriteSchoolId($request->integer('school_id') ?: null);

        $resultat = $this->service->assurerLot($schoolId);

        $message = $resultat['crees'] > 0
            ? "{$resultat['crees']} accès parent ouvert(s)."
            : 'Aucun nouvel accès à ouvrir — tous les tuteurs avec un numéro valide en ont déjà un.';

        return ApiResponse::success($resultat, $message);
    }

    /** Document confidentiel des identifiants parent, à distribuer en main propre — même principe que celui du personnel. */
    public function identifiantsParentPdf(): Response
    {
        $schoolIds = Tenant::schoolIds();
        $schools = School::whereIn('id', $schoolIds)->orderBy('name')->get();

        if (Tenant::isAggregate()) {
            $documents = $schools->map(fn (School $school) => [
                'donnees' => $this->service->identifiants($school->id),
                'school' => $school,
            ])->all();
            $pdf = (new IdentifiantsGenerator)->buildMany($documents);
            $nom = 'identifiants-parents-toutes-les-ecoles';
        } else {
            $school = $schools->firstOrFail();
            $pdf = (new IdentifiantsGenerator)->build($this->service->identifiants($school->id), $school);
            $nom = 'identifiants-parents-'.Str::slug($school->name);
        }

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nom.'.pdf"',
        ]);
    }
}
