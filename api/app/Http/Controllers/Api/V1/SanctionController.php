<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Api\V1\Concerns\RestreintParTypeEcole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreSanctionRequest;
use App\Http\Requests\Api\V1\UpdateSanctionRequest;
use App\Http\Resources\Api\V1\SanctionResource;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Sanction;
use App\Models\School;
use App\Models\Trimestre;
use App\Support\Pdf\MpdfFactory;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sanctions disciplinaires du secondaire : corvée, exclusion temporaire ou
 * définitive. Le primaire et la maternelle suivent l'assiduité de leurs élèves
 * — les absences restent relevées à l'appel — mais ne prononcent pas ce type
 * de mesure contre de jeunes enfants.
 */
class SanctionController extends Controller
{
    use RestreintParTypeEcole;

    public function index(Request $request): JsonResponse
    {
        if ($refus = $this->refuserSaufPour('secondaire')) {
            return $refus;
        }

        $sanctions = Sanction::forSchool(Tenant::schoolIds())
            ->with(['eleve.school', 'classe', 'enregistrePar'])
            ->when($request->integer('eleve_id'), fn ($q, $id) => $q->where('eleve_id', $id))
            ->when($request->integer('classe_id'), fn ($q, $id) => $q->where('classe_id', $id))
            ->when($request->integer('trimestre_id'), fn ($q, $id) => $q->where('trimestre_id', $id))
            ->latest('date_sanction')
            ->get();

        return ApiResponse::success(SanctionResource::collection($sanctions));
    }

    public function store(StoreSanctionRequest $request): JsonResponse
    {
        if ($refus = $this->refuserSaufPour('secondaire')) {
            return $refus;
        }

        $eleve = Eleve::forSchool(Tenant::schoolIds())->with('school')->findOrFail($request->integer('eleve_id'));

        // En mode agrégé, refuserSaufPour() ne bloque plus rien (l'école de
        // repli du super admin n'a aucune raison d'être secondaire) : c'est
        // donc ici, sur l'école réelle de l'élève choisi, que la règle
        // s'applique.
        if ($eleve->school?->type !== 'secondaire') {
            return ApiResponse::error($this->messageRefus(), 422);
        }

        if (! $eleve->classe_id) {
            return ApiResponse::error("Cet élève n'est affecté à aucune classe.", 422);
        }

        $donnees = $request->validated();

        // Une exclusion temporaire se borne dans le temps à partir de sa seule
        // durée : personne ne devrait avoir à ressaisir « du X au Y » alors que
        // le point de départ est déjà connu (la date de la sanction elle-même).
        if ($donnees['type'] === 'exclusion_temporaire' && ! empty($donnees['duree_jours'])) {
            $debut = Carbon::parse($donnees['date_sanction']);
            $donnees['date_debut'] = $debut->toDateString();
            $donnees['date_fin'] = $debut->copy()->addDays($donnees['duree_jours'] - 1)->toDateString();
        }

        $sanction = Sanction::create([
            ...$donnees,
            'classe_id' => $eleve->classe_id,
            // Le défaut SQL de la colonne ne se reflète pas sur l'instance
            // Eloquent tant qu'elle n'est pas rechargée : sans ça la réponse
            // de création annonce `statut: null` là où la base contient bien
            // « en_attente ».
            'statut' => 'en_attente',
            'enregistre_par' => $request->user()->personnel?->id,
        ])->load(['eleve.school', 'classe', 'enregistrePar']);

        return ApiResponse::created(new SanctionResource($sanction), 'Sanction enregistrée.');
    }

    /**
     * Seuls le statut (confirmation ou annulation par le conseil de
     * discipline), le commentaire et l'impact sur le bulletin se corrigent
     * après coup — la sanction elle-même (élève, type, date) reste telle
     * qu'enregistrée, comme une pièce d'un dossier.
     */
    public function update(UpdateSanctionRequest $request, int $id): JsonResponse
    {
        if ($refus = $this->refuserSaufPour('secondaire')) {
            return $refus;
        }

        $sanction = Sanction::forSchool(Tenant::schoolIds())->findOrFail($id);
        $sanction->update($request->validated());

        return ApiResponse::success(new SanctionResource($sanction->fresh(['eleve.school', 'classe', 'enregistrePar'])), 'Sanction mise à jour.');
    }

    public function destroy(int $id): JsonResponse
    {
        $sanction = Sanction::forSchool(Tenant::schoolIds())->findOrFail($id);
        $sanction->delete();

        return ApiResponse::success(message: 'Sanction supprimée.');
    }

    /**
     * Dossier disciplinaire d'un élève : son historique complet et un résumé
     * (total, sanctions en cours, exclusion active) — le pendant du
     * `DossierDisciplinaireScreen` de _smapp, calculé à la volée plutôt que
     * stocké, pour ne jamais désynchroniser d'une sanction confirmée après coup.
     */
    public function dossier(int $eleveId): JsonResponse
    {
        if ($refus = $this->refuserSaufPour('secondaire')) {
            return $refus;
        }

        $eleve = Eleve::forSchool(Tenant::schoolIds())->findOrFail($eleveId);

        $sanctions = Sanction::where('eleve_id', $eleve->id)
            ->with(['classe', 'enregistrePar'])
            ->latest('date_sanction')
            ->get();

        $exclusionActive = $sanctions->first(function (Sanction $s) {
            if ($s->statut !== 'confirmee') {
                return false;
            }

            return $s->type === 'exclusion_definitive'
                || ($s->type === 'exclusion_temporaire' && $s->date_fin && ! $s->date_fin->isPast());
        });

        return ApiResponse::success([
            'total_sanctions' => $sanctions->count(),
            'sanctions_en_cours' => $sanctions->where('statut', 'en_attente')->count(),
            'est_exclu' => $exclusionActive !== null,
            'motif_exclusion' => $exclusionActive?->motif,
            'date_exclusion' => $exclusionActive?->date_sanction?->format('Y-m-d'),
            'sanctions' => SanctionResource::collection($sanctions),
        ]);
    }

    /**
     * Procès-verbal du conseil de discipline : les sanctions encore en attente
     * d'une décision, mises en forme pour être imprimées, complétées à la main
     * en séance puis signées — le pendant du `GET /sanctions/pv-conseil` de
     * _smapp. Filtrable par classe, sinon tout l'établissement pour ce
     * trimestre.
     */
    public function pvConseilPdf(Request $request): Response
    {
        if ($refus = $this->refuserSaufPour('secondaire')) {
            return $refus;
        }

        // Le trimestre appartient à une seule école : on en déduit celle du PV
        // plutôt que d'exiger un `school_id` séparé — pas d'ambiguïté possible
        // même en mode agrégé, puisque l'id est cherché parmi celles accessibles.
        $trimestre = Trimestre::whereHas(
            'anneeScolaire',
            fn ($q) => $q->whereIn('school_id', Tenant::schoolIds())
        )->with('anneeScolaire')->findOrFail($request->integer('trimestre_id'));

        $schoolId = $trimestre->anneeScolaire->school_id;

        $classe = $request->integer('classe_id')
            ? Classe::forSchool($schoolId)->findOrFail($request->integer('classe_id'))
            : null;

        $sanctions = Sanction::forSchool($schoolId)
            ->where('trimestre_id', $trimestre->id)
            ->where('statut', 'en_attente')
            ->when($classe, fn ($q) => $q->where('classe_id', $classe->id))
            ->with(['eleve', 'classe'])
            ->orderBy('date_sanction')
            ->get();

        $school = School::findOrFail($schoolId);

        return MpdfFactory::streamFromView('pdf.pv-conseil-discipline', [
            'school' => $school,
            'trimestre' => $trimestre,
            'classe' => $classe,
            'sanctions' => $sanctions,
        ], 'pv-conseil-discipline.pdf', school: $school);
    }

    protected function messageRefus(): string
    {
        return 'Cet établissement ne prononce pas de sanctions disciplinaires.';
    }
}
